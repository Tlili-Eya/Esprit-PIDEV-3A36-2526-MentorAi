<?php
namespace App\Controller;

use App\Entity\ProfilApprentissage;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PersonalityController extends AbstractController
{
    #[Route('/personality', name: 'personality_test')]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Get the current logged user or use a default user
        $user = $this->getUser();
        if (!$user) {
            // Fallback to a default user with ID 1 if no user is logged in
            $user = $entityManager->getRepository(Utilisateur::class)->find(1);
        }
        
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable');
        }

        // Get or create the learning profile for this user
        $profile = $entityManager->getRepository(ProfilApprentissage::class)
            ->findOneBy(['Utilisateur' => $user]);

        if (!$profile) {
            $profile = new ProfilApprentissage();
            $profile->setUtilisateur($user);
        }
        // Tableau des questions (12 questions)
        $questions = [
            ['text' => 'À une fête, préférez-vous :', 'axis' => 'EI', 'option_a' => 'Interagir avec beaucoup de personnes, y compris des inconnus', 'option_b' => 'Interagir avec quelques personnes que vous connaissez', 'value_a' => 'E', 'value_b' => 'I'],
            ['text' => 'Aux fêtes, avez-vous tendance à :', 'axis' => 'EI', 'option_a' => 'Rester tard, avec de l’énergie croissante', 'option_b' => 'Partir tôt, avec une énergie diminuée', 'value_a' => 'E', 'value_b' => 'I'],
            ['text' => 'En société, êtes-vous plutôt :', 'axis' => 'EI', 'option_a' => 'Celui qui initie la conversation', 'option_b' => 'Celui qui attend qu’on l’aborde', 'value_a' => 'E', 'value_b' => 'I'],
            ['text' => 'Êtes-vous plutôt :', 'axis' => 'SN', 'option_a' => 'Réaliste que spéculatif', 'option_b' => 'Spéculatif que réaliste', 'value_a' => 'S', 'value_b' => 'N'],
            ['text' => 'Êtes-vous plus attiré par :', 'axis' => 'SN', 'option_a' => 'Les gens sensés', 'option_b' => 'Les gens imaginatifs', 'value_a' => 'S', 'value_b' => 'N'],
            ['text' => 'Pour faire les choses ordinaires, êtes-vous plus enclin à :', 'axis' => 'SN', 'option_a' => 'Les faire de la manière habituelle', 'option_b' => 'Les faire à votre façon', 'value_a' => 'S', 'value_b' => 'N'],
            ['text' => 'Êtes-vous plus impressionné par :', 'axis' => 'TF', 'option_a' => 'Les principes', 'option_b' => 'Les émotions', 'value_a' => 'T', 'value_b' => 'F'],
            ['text' => 'En abordant les autres, votre inclination est-elle d’être plutôt :', 'axis' => 'TF', 'option_a' => 'Objectif', 'option_b' => 'Personnel', 'value_a' => 'T', 'value_b' => 'F'],
            ['text' => 'Êtes-vous plus à l’aise pour prendre des décisions :', 'axis' => 'TF', 'option_a' => 'Logiques', 'option_b' => 'Basées sur les valeurs', 'value_a' => 'T', 'value_b' => 'F'],
            ['text' => 'Préférez-vous travailler :', 'axis' => 'JP', 'option_a' => 'Avec des délais', 'option_b' => '« Quand cela vient »', 'value_a' => 'J', 'value_b' => 'P'],
            ['text' => 'Qu’est-ce qui vous dérange le plus :', 'axis' => 'JP', 'option_a' => 'Les choses incomplètes', 'option_b' => 'Les choses terminées', 'value_a' => 'J', 'value_b' => 'P'],
            ['text' => 'Préférez-vous que les choses soient :', 'axis' => 'JP', 'option_a' => 'Réglées et décidées', 'option_b' => 'Non réglées et indécises', 'value_a' => 'J', 'value_b' => 'P'],
        ];

        $result = null;

        if ($request->isMethod('POST')) {
            $answers = $request->request->all('answers'); // tableau associatif [index_question => 'a' ou 'b']

            // Validation : vérifier que toutes les questions ont une réponse
            if (count($answers) !== count($questions)) {
                $this->addFlash('error', 'Veuillez répondre à toutes les questions.');
            } else {
                // Compter les occurrences de chaque lettre
                $counters = ['E' => 0, 'I' => 0, 'S' => 0, 'N' => 0, 'T' => 0, 'F' => 0, 'J' => 0, 'P' => 0];

                foreach ($answers as $idx => $answer) {
                    $question = $questions[$idx];
                    $value = $answer === 'a' ? $question['value_a'] : $question['value_b'];
                    $counters[$value]++;
                }

                // Déterminer la lettre dominante pour chaque axe
                $type = '';
                $type .= ($counters['E'] >= $counters['I']) ? 'E' : 'I';
                $type .= ($counters['S'] >= $counters['N']) ? 'S' : 'N';
                $type .= ($counters['T'] >= $counters['F']) ? 'T' : 'F';
                $type .= ($counters['J'] >= $counters['P']) ? 'J' : 'P';

                // Save the personality type to the database
                $profile->setTypePers($type);
                $entityManager->persist($profile);
                $entityManager->flush();

                $result = $type;
            }
        }

        return $this->render('front/personality.html.twig', [
            'questions' => $questions,
            'result' => $result,
        ]);
    }
}