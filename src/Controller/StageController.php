<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\Stage;
use App\Form\StageType;
use App\Repository\StageRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StageController extends AbstractController
{
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_ETUDIANT') or is_granted('ROLE_ENSEIGNANT') or is_granted('ROLE_ENTREPRISE')")]
    #[Route('/stage', name: 'app_stage')]
    public function index(StageRepository $stageRepository): Response
    {
        $stage = $stageRepository->findBy([], ['id' => 'ASC', 'titre' => 'ASC', 'description' => 'ASC']);

        return $this->render('stage/index.html.twig', [
            'stages' => $stage,
        ]);
    }

    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_ETUDIANT') or is_granted('ROLE_ENSEIGNANT') or is_granted('ROLE_ENTREPRISE')")]
    #[Route('/stage/{id}', name: 'app_stage_show', requirements: ['id' => '\d+'])]
    public function show(Stage $stage): Response
    {
        return $this->render('stage/show.html.twig', ['stage' => $stage]);
    }

    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_ENTREPRISE')")]
    #[Route('/stage/detail-candidature/{id}/', name: 'app_stage_detail-candidature', requirements: ['id' => '\d+'])]
    public function candidatureShow(Candidature $candidature): Response
    {
        if ($this->getUser()->getId() !== $candidature->getIdStage()->getAuthor()->getId() and !in_array("ROLE_ADMIN", $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_stage');
        }

        return $this->render('stage/candidature_show.html.twig', ['candidature' => $candidature]);
    }

    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_ENTREPRISE')")]
    #[Route('/stage/{id}/retenues/', name: 'app_stage_retenues', requirements: ['id' => '\d+'])]
    public function candidatureRetenue(Stage $stage): Response
    {
        if ($this->getUser()->getId() !== $stage->getAuthor()->getId() and !in_array("ROLE_ADMIN", $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_stage');
        }

        return $this->render('stage/candidature_retenues.html.twig', ['stage' => $stage]);
    }

    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_ENTREPRISE')")]
    #[Route('/stage/{id}/update', name: 'app_stage_update', requirements: ['id' => '\d+'])]
    public function update(Stage $stage, Request $request, ManagerRegistry $doctrine): Response
    {
        if (($this->getUser()->getId() !== $stage->getAuthor()->getId()) and !in_array("ROLE_ADMIN", $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_stage');
        }

        $form = $this->createForm(StageType::class, $stage);
        $form->add('save', SubmitType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $doctrine->getManager();

            if (!$stage) {
                throw $this->createNotFoundException('No stage found for id '.$stage->getId());
            }

            $stage->setTitre($form->getData()->getTitre());
            $stage->setDescription($form->getData()->getDescription());
            $entityManager->flush();

            return $this->redirectToRoute('app_profil_stages');
        }

        return $this->render('stage/update.html.twig', [
            'stage' => $stage,
            'form' => $form->createView(),
        ]);
    }

    #[Security("is_granted('ROLE_ADMIN') or  is_granted('ROLE_ENTREPRISE')")]
    #[Route('/stage/create', name: 'app_stage_create')]
    public function create(ManagerRegistry $doctrine, Request $request): Response
    {
        $stage = new Stage();

        $form = $this->createForm(StageType::class, $stage);
        $form->add('save', SubmitType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $stage->setTitre($form->getData()->getTitre());
            $stage->setDescription($form->getData()->getDescription());
            $stage->setAuthor($this->getUser());

            $em = $doctrine->getManager();
            $em->persist($stage);
            $em->flush();

            return $this->redirectToRoute('app_stage', [
                'id' => $stage->getId(),
            ]);
        }

        return $this->render('stage/create.html.twig', [
            'stage' => $stage,
            'form' => $form->createView(),
        ]);
    }

    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_ENTREPRISE')")]
    #[Route('/stage/{id}/delete', name: 'app_stage_delete', requirements: ['id' => '\d+'])]
    public function delete(Stage $stage, Request $request, ManagerRegistry $doctrine): Response
    {
        if ($this->getUser()->getId() !== $stage->getAuthor()->getId() and !in_array("ROLE_ADMIN", $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_stage');
        }

        $form = $this->createFormBuilder()
            ->add('delete', SubmitType::class, [
                'attr' => ['class' => 'projet__form__delete'],
            ])
            ->add('cancel', SubmitType::class, [
                'attr' => ['class' => 'projet__form__cancel'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $doctrine->getManager();

            if ($form->getClickedButton() === $form->get('delete')) {
                $entityManager->remove($stage);
                $entityManager->flush();

                return $this->redirectToRoute('app_profil_stages');
            }

            if ($form->getClickedButton() === $form->get('cancel')) {
                return $this->redirectToRoute('app_stage_show', [
                    'id' => $stage->getId(),
                ]);
            }
        }

        return $this->render('stage/delete.html.twig', [
            'stage' => $stage,
            'form' => $form->createView(),
        ]);
    }
}
