<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FAQController extends AbstractController
{
    #[Route('/faq', name: 'app_f_a_q')]
    public function index(): Response
    {
        return $this->render('faq/index.html.twig', [
            'controller_name' => 'FAQController',
        ]);
    }
}
