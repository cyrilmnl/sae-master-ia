<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\Stage;
use App\Form\CandidatureType;
use App\Repository\CandidatureRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class CandidatureController extends AbstractController
{
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_ETUDIANT')")]
    #[Route('/candidature/new', name: 'app_candidature_new')]
    public function new(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger, CandidatureRepository $candidatures)
    {
        $em = $doctrine->getManager();
        $stage = $em->getRepository(Stage::class)->find($request->query->get('idStage'));

        $candidature = new Candidature();
        $form = $this->createForm(CandidatureType::class, $candidature);
        $form->add('Valider', SubmitType::class);

        $areEqual = $candidatures->findEqual($this->getUser()->getId(), $stage->getId());
        if (0 != count($areEqual)) {
            return $this->redirectToRoute('app_stage');
        }

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $cvFile */
            $cvFile = $form->get('cvFilename')->getData();

            if ($cvFile) {
                $originalFilename = pathinfo($cvFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$cvFile->guessExtension();

                try {
                    $cvFile->move(
                        $this->getParameter('cvs_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }

                $candidature->setCvFilename($newFilename);
            }

            if ($form->isSubmitted() && $form->isValid()) {
                $candidature->setIdUser($this->getUser());
                $candidature->setIdStage($stage);
                $candidature->setDate(new \DateTimeImmutable('now'));
                $candidature->setRetenue(false);
                $em->persist($candidature);
                $em->flush();
            }

            return $this->redirectToRoute('app_stage');
        }

        return $this->renderForm('candidature/new.html.twig', [
            'form' => $form,
            'stage' => $stage,
        ]);
    }

    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_ETUDIANT')")]
    #[Route('/candidature/{id}/update', name: 'app_candidature_update', requirements: ['id' => '\d+'])]
    public function update(Candidature $candidature, Request $request, ManagerRegistry $doctrine, SluggerInterface $slugger): Response
    {
        if ($this->getUser()->getId() !== $candidature->getIdUser()->getId() and !in_array("ROLE_ADMIN", $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_profil_candidatures');
        }

        $em = $doctrine->getManager();

        $form = $this->createForm(CandidatureType::class, $candidature);
        $form->add('Valider', SubmitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $cvFile */
            $cvFile = $form->get('cvFilename')->getData();

            if ($cvFile) {
                $originalFilename = pathinfo($cvFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$cvFile->guessExtension();

                try {
                    $cvFile->move(
                        $this->getParameter('cvs_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }

                $candidature->setCvFilename($newFilename);
            }

            if ($form->isSubmitted() && $form->isValid()) {
                $candidature->setDate(new \DateTimeImmutable('now'));
                $em->persist($candidature);
                $em->flush();
            }

            return $this->redirectToRoute('app_profil_candidatures');
        }

        return $this->render('candidature/update.html.twig', [
            'candidature' => $candidature,
            'form' => $form->createView(),
        ]);
    }

    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_ETUDIANT')")]
    #[Route('/candidature/{id}/delete', name: 'app_candidature_delete', requirements: ['id' => '\d+'])]
    public function delete(Candidature $candidature, Request $request, ManagerRegistry $doctrine): Response
    {
        if ($this->getUser()->getId() !== $candidature->getIdUser()->getId() and !in_array("ROLE_ADMIN", $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_profil_candidatures');
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
                $entityManager->remove($candidature);
                $entityManager->flush();

                return $this->redirectToRoute('app_profil_candidatures');
            }

            if ($form->getClickedButton() === $form->get('cancel')) {
                return $this->redirectToRoute('app_profil_candidatures');
            }
        }

        return $this->render('candidature/delete.html.twig', [
            'candidature' => $candidature,
            'form' => $form->createView(),
        ]);
    }

    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_ENTREPRISE')")]
    #[Route('/candidature/{id}/retenir', name: 'app_candidature_retenir', requirements: ['id' => '\d+'])]
    public function retenir(Candidature $candidature, Request $request, ManagerRegistry $doctrine, SluggerInterface $slugger): Response
    {
        if ($this->getUser()->getId() !== $candidature->getIdStage()->getAuthor()->getId() and !in_array("ROLE_ADMIN", $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_stage_show', ['id' => $candidature->getIdStage()->getId()]);
        }

        $form = $this->createFormBuilder()
            ->add('save', SubmitType::class)
            ->add('cancel', SubmitType::class)
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $doctrine->getManager();

            if ($form->getClickedButton() === $form->get('save')) {
                $candidature->setRetenue(true);

                $em->persist($candidature);
                $em->flush();

                return $this->redirectToRoute('app_stage_show', ['id' => $candidature->getIdStage()->getId()]);
            }
            if ($form->getClickedButton() === $form->get('cancel')) {
                return $this->redirectToRoute('app_stage_show', ['id' => $candidature->getIdStage()->getId()]);
            }
        }

        return $this->render('candidature/retenir.html.twig', [
            'candidature' => $candidature,
            'form' => $form->createView(),
        ]);
    }

    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_ENTREPRISE')")]
    #[Route('/candidature/{id}/abandon', name: 'app_candidature_abandon', requirements: ['id' => '\d+'])]
    public function abandonner(Candidature $candidature, Request $request, ManagerRegistry $doctrine, SluggerInterface $slugger): Response
    {
        if ($this->getUser()->getId() !== $candidature->getIdStage()->getAuthor()->getId() and !in_array("ROLE_ADMIN", $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_stage_show', ['id' => $candidature->getIdStage()->getId()]);
        }

        $form = $this->createFormBuilder()
            ->add('save', SubmitType::class)
            ->add('cancel', SubmitType::class)
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $doctrine->getManager();

            if ($form->getClickedButton() === $form->get('save')) {
                $candidature->setRetenue(false);

                $em->persist($candidature);
                $em->flush();

                return $this->redirectToRoute('app_stage_show', ['id' => $candidature->getIdStage()->getId()]);
            }
            if ($form->getClickedButton() === $form->get('cancel')) {
                return $this->redirectToRoute('app_stage_show', ['id' => $candidature->getIdStage()->getId()]);
            }
        }

        return $this->render('candidature/abandon.html.twig', [
            'candidature' => $candidature,
            'form' => $form->createView(),
        ]);
    }
}
