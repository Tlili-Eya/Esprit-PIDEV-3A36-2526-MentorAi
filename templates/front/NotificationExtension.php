<?php

namespace App\Twig;

use App\Entity\Utilisateur;
use App\Repository\FeedbackRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private Security $security,
        private FeedbackRepository $feedbackRepository,
        private RequestStack $requestStack
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_new_treated_count', [$this, 'getNewTreatedCount']),
        ];
    }

    public function getNewTreatedCount(): int
    {
        $user = $this->security->getUser();
        
        if (!$user instanceof Utilisateur) {
            return 0;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return 0;
        }

        $feedbacks = $this->feedbackRepository->findBy(['utilisateur' => $user]);
        $session = $request->getSession();
        $seenFeedbackIds = $session->get('seen_treated_feedbacks', []);
        
        $newTreatedCount = 0;
        foreach ($feedbacks as $feedback) {
            $etat = strtolower($feedback->getEtatfeedback() ?? '');
            if (($etat === 'traite' || $etat === 'traité') && !in_array($feedback->getId(), $seenFeedbackIds)) {
                $newTreatedCount++;
            }
        }
        
        return $newTreatedCount;
    }
}