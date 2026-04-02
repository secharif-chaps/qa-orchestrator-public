<?php

namespace App\UserInterface\Http;

use App\Application\SyncActionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Exception\ExceptionInterface as MessengerExceptionInterface;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[AsController]
class WebhookController extends AbstractController
{
    use HandleTrait;
    private const string MODE_SYNC = 'sync';
    private const string MODE_ASYNC = 'async';
    private const string ALLOWED_MESSAGE_NAMESPACE = 'App\\Application\\';
    private const string HEADER_MESSAGE_TYPE = 'X-Message-Type';
    private const string HEADER_GROUP_SERIALIZATION = 'X-Serialization-Groups';

    public function __construct(
        private readonly SerializerInterface $serializer,
        private MessageBusInterface $messageBus,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route('/api/webhook', name: 'webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        if ('json' !== $request->getContentTypeFormat()) {
            throw new BadRequestHttpException('Invalid content type, expected application/json');
        }

        if (!$request->headers->has(self::HEADER_MESSAGE_TYPE)) {
            throw new BadRequestHttpException('Missing X-Message-Type header');
        }

        $messageClass = $request->headers->get(self::HEADER_MESSAGE_TYPE, '');

        if (empty($messageClass)) {
            throw new BadRequestHttpException('Missing X-Message-Type header');
        }

        if (!str_starts_with($messageClass, self::ALLOWED_MESSAGE_NAMESPACE)) {
            throw new BadRequestHttpException(\sprintf('Invalid X-Message-Type header, given: "%s"', $messageClass));
        }

        if (!class_exists($messageClass)) {
            throw new BadRequestHttpException(\sprintf('Message class does not exist: "%s"', $messageClass));
        }

        $groupSerialization = $request->headers->get(self::HEADER_GROUP_SERIALIZATION, 'watch_file:read');

        if (!empty($groupSerialization) && !preg_match('/[a-z_:,]+/', $groupSerialization)) {
            throw new BadRequestHttpException('Invalid X-Serialization-Groups header');
        }

        $groupSerializations = explode(',', $groupSerialization);

        $content = $request->getContent();
        if (empty($content)) {
            throw new BadRequestHttpException('Empty request content');
        }

        try {
            $this->logger?->info(\sprintf('Webhook received: %s', $messageClass));

            $message = $this->serializer->deserialize($request->getContent(), $messageClass, 'json');
        } catch (SerializerExceptionInterface $e) {
            $this->logger?->error(
                \sprintf('An exception occurred while deserializing the message: %s', $e->getMessage())
            );

            throw new BadRequestHttpException('Invalid message type', previous: $e);
        }

        $dataResponse = [
            'status' => 'ok',
            'mode' => self::MODE_ASYNC,
        ];

        if ($message instanceof SyncActionInterface) {
            $dataResponse['mode'] = self::MODE_SYNC;
            $dataResponse['response'] = $this->handle($message);

            try {
                $jsonResponse = $this->serializer->serialize($dataResponse, 'json', [
                    'groups' => $groupSerializations,
                ]);

                return new JsonResponse($jsonResponse, json: true);
            } catch (SerializerExceptionInterface $e) {
                $this->logger?->error(
                    \sprintf('An exception occurred while serializing the response: %s', $e->getMessage())
                );

                throw new BadRequestHttpException('An exception occurred while serializing the response', previous: $e);
            }
        }

        try {
            $this->messageBus->dispatch($message);
        } catch (MessengerExceptionInterface $e) {
            $this->logger?->error(
                \sprintf('An exception occurred while dispatching the message: %s', $e->getMessage())
            );

            throw new BadRequestHttpException('An exception occurred while dispatching the message', previous: $e);
        }

        return new JsonResponse($dataResponse);
    }
}
