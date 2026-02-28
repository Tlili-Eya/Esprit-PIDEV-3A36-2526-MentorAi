<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\HttpFoundation\Response;

class CustomAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('_username', '');
        $hCaptchaResponse = $request->request->get('h-captcha-response');

        // Verify hCaptcha
        if (empty($hCaptchaResponse)) {
             throw new \Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException('Veuillez résoudre le captcha.');
        }

        $verifyUrl = 'https://hcaptcha.com/siteverify';
        $data = [
            'secret' => $_ENV['HCAPTCHA_SECRET_KEY'],
            'response' => $hCaptchaResponse,
            'remoteip' => $request->getClientIp()
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context  = stream_context_create($options);
        $result = file_get_contents($verifyUrl, false, $context);
        $responseData = json_decode($result);

        if (!$responseData->success) {
            throw new \Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException('Captcha invalide. Veuillez réessayer.');
        }

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($request->request->get('_password', '')),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Récupérer l'utilisateur connecté
        $user = $token->getUser();
        $roles = $user->getRoles();

        // #region agent log
        $logEntry = json_encode([
            'id' => 'log_' . uniqid(),
            'timestamp' => (int) (microtime(true) * 1000),
            'location' => 'CustomAuthenticator.php:onAuthenticationSuccess',
            'message' => 'onAuthenticationSuccess roles & firewall',
            'runId' => 'pre-fix',
            'hypothesisId' => 'H1-H3',
            'data' => [
                'roles' => $roles,
                'firewallName' => $firewallName,
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        @file_put_contents(
            __DIR__ . '/../../.cursor/debug.log',
            $logEntry . PHP_EOL,
            FILE_APPEND
        );
        // #endregion agent log

        // Check roles and redirect accordingly
        // ROLE_ADMIN -> back_home
        if (in_array('ROLE_ADMIN', $roles, true)) {
             return new RedirectResponse($this->urlGenerator->generate('back_home'));
        }

        // ROLE_ADMINISTRATEUR -> back_administrateur (Back Admin)
        if (in_array('ROLE_ADMINISTRATEUR', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('back_administrateur'));
        }

        // ROLE_ADMINM -> app_admin_dashboard (Front Admin)
        if (in_array('ROLE_ADMINM', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
        }

        // ROLE_ENSEIGNANT -> app_enseignant_dashboard (Front Enseignant)
        if (in_array('ROLE_ENSEIGNANT', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_enseignant_dashboard'));
        }

        // ✅ SI ÉTUDIANT ou défaut → page front normale
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}