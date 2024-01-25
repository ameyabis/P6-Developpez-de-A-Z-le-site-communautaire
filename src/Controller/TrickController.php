<?php

namespace App\Controller;

use App\Entity\Trick;
use App\Entity\User;
use App\Form\CreateTrickType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class TrickController extends AbstractController
{
    public function __construct(
        public EntityManagerInterface $em,
    ) {
    }

    #[Route(path: '/formTrick', name: 'form_trick')]
    public function createFormTrick(
        Request $request,
    #[CurrentUser] ?User $user
    ): Response {
        $trick = new Trick();
        $dateNow = new \DateTime();
        $dateNow->setTimezone(new \DateTimeZone('Europe/Paris'));
        $dateNow->format('Y-m-d H:i:s');

        $formTrick = $this->createForm(CreateTrickType::class, $trick);
        $formTrick->handleRequest($request);

        if ($formTrick->isSubmitted() && $formTrick->isValid()) {
            $completedForm = $request->request->all()["create_trick"];
            $trick->setName($completedForm['name']);
            $trick->setGroupTrick($completedForm['groupTrick']);
            $trick->setDescription($completedForm['description']);
            $trick->setDateCreate($dateNow);
            $trick->setUser($user);

            $this->em->persist($trick);
            $this->em->flush();

            return $this->render('home/homePage.html.twig', [
                'tricks' => $this->getTricks()
            ]);
        } else {
            return $this->render('crud/formCreateTrick.html.twig', [
                'formTrick' => $formTrick,
            ]);
        }
    }

    #[Route(path: '/trick/{id}', name: 'one_trick')]
    public function showOneTrick(int $id): Response
    {
        $trick = $this->em->getRepository(Trick::class)->find($id);

        return $this->render('page/trick.html.twig', [
            'trick' => $trick,
        ]);
    }

    public function getTricks(): array
    {
        $tricks = $this->em->getRepository(Trick::class)->findAll();

        return $tricks;
    }

    #[Route(path: '/deleteTrick/{id}', name: 'delete_trick')]
    public function deleteTrick(int $id): Response  //Response
    {
        $trick = $this->em->getRepository(Trick::class)->find($id);

        $this->em->remove($trick);
        $this->em->flush();

        return $this->redirectToRoute('app_home');
    }
}
