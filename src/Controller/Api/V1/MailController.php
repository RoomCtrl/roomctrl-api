<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\MailService;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Mails')]
class MailController extends AbstractController
{
    #[Route('/send_mail', name: 'send_mail', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/send_mail',
        description: 'Allows anyone to send an email',
        summary: 'Send an email',
        requestBody: new OA\RequestBody(
            description: 'Email data',
            required: true,
            content: new OA\JsonContent(
                required: ['to', 'subject', 'content'],
                properties: [
                    new OA\Property(property: 'to', description: 'Email recipient', type: 'string', example: 'recipient@example.com'),
                    new OA\Property(property: 'subject', description: 'Email subject', type: 'string', example: 'Test Subject'),
                    new OA\Property(property: 'content', description: 'Email body content', type: 'string', example: 'This is the email content')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Email sent successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Email has been sent successfully')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - missing or invalid parameters',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Missing required parameters')
                    ]
                )
            )
        ]
    )]
    public function sendMail(Request $request, MailService $mailService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $validationError = $mailService->validateMailFields($data, ['to', 'subject', 'content'], 'to');
        if ($validationError) {
            return $this->json($validationError, $validationError['code']);
        }
        try {
            $result = $mailService->sendEmail($data);
            return $this->json($result);
        } catch (Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/contact_mail', name: 'contact_form', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/contact_mail',
        description: 'Send a message via contact form',
        summary: 'Send a contact form message',
        requestBody: new OA\RequestBody(
            description: 'Contact form data',
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'subject', 'message'],
                properties: [
                    new OA\Property(property: 'name', description: 'Sender name', type: 'string', example: 'John Smith'),
                    new OA\Property(property: 'email', description: 'Sender email', type: 'string', example: 'john.smith@example.com'),
                    new OA\Property(property: 'subject', description: 'Message subject', type: 'string', example: 'Service inquiry'),
                    new OA\Property(property: 'message', description: 'Message content', type: 'string', example: 'Hello, I would like to inquire about your services...')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Message sent successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Your message has been sent successfully')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - missing or invalid parameters',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Missing required parameters')
                    ]
                )
            )
        ]
    )]
    public function contactForm(Request $request, MailService $mailService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $validationError = $mailService->validateMailFields($data, ['name', 'email', 'subject', 'message'], 'email');
        if ($validationError) {
            return $this->json($validationError, $validationError['code']);
        }
        try {
            $mailService->sendContactFormEmail($data);
            return $this->json([
                'code' => 200,
                'message' => 'Your message has been sent successfully'
            ]);
        } catch (Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Failed to send message: ' . $e->getMessage()
            ], 500);
        }
    }
}
