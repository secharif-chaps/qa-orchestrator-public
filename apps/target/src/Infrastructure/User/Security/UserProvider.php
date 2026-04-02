<?php

declare(strict_types=1);

namespace App\Infrastructure\User\Security;

use App\Domain\User\User;
use App\Domain\User\UserGatewayInterface;
use App\Domain\User\UserNotFoundException as UserNotFoundExceptionDomain;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\AttributesBasedUserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Webmozart\Assert\Assert;

use function Symfony\Component\String\u;

/**
 * @implements AttributesBasedUserProviderInterface<User>
 */
class UserProvider implements AttributesBasedUserProviderInterface
{
    public function __construct(
        private readonly UserGatewayInterface $userGateway,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function loadUserByIdentifier(string $identifier, array $attributes = []): UserInterface
    {
        try {
            $user = $this->userGateway->get($identifier);

            if (\count($attributes) > 0) {
                $this->updateLocalUser($user, $attributes);
            }

            return $user;
        } catch (UserNotFoundExceptionDomain) {
            // silently ignore the exception if the user is not found
            // if his email is unique, it will be created later
        }

        // If the user does not exist, we create a new one based on the attributes provided
        $userFromAttributes = $this->createUserFromAttributes($attributes);

        try {
            // for check email uniqueness
            $email = $userFromAttributes->getEmail();
            if (!empty($email)) {
                $this->userGateway->getByEmail($email);

                // If we reach this point, it means the email address already exists in the database
                throw new \InvalidArgumentException(\sprintf(
                    'User with email %s already exists.',
                    $userFromAttributes->getEmail()
                ));
            }
        } catch (UserNotFoundExceptionDomain) {
            // silently ignore the exception if the user is not found
        }

        // Save the new user
        $this->logger->info(
            \sprintf('User %s not found, this email is unique, so we can create a new user with it', $identifier),
            [
                'identifier' => $identifier,
                'attributes' => $attributes,
            ],
        );

        $this->userGateway->save($userFromAttributes);

        return $userFromAttributes;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException('Invalid user class');
        }

        $id = $user->getId();
        if (null === $id) {
            throw new \InvalidArgumentException('User ID is null');
        }

        return $this->userGateway->get($id);
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createUserFromAttributes(array $attributes): User
    {
        $sub = $attributes['sub'] ?? null;
        Assert::notNull($sub, 'The "sub" claim is required to create a user.');
        Assert::string($sub, 'The "sub" claim must be a string.');
        Assert::notEmpty($sub, 'The "sub" claim cannot be empty.');

        $email = $attributes['email'] ?? null;
        if (null !== $email) {
            Assert::string($email, 'The "email" claim must be a string.');
            Assert::notEmpty($email, 'The "email" claim cannot be empty.');
            Assert::true(
                false !== filter_var($email, \FILTER_VALIDATE_EMAIL),
                'The "email" claim must be a valid email address.'
            );
        }

        $userName = $attributes['preferred_username'] ?? $attributes['username'] ?? null;
        if (null === $userName) {
            $userName = $email ?? 'user_' . $sub;
        } else {
            Assert::string($userName, 'The "preferred_username" or "username" claim must be a string.');
            Assert::notEmpty($userName, 'The "preferred_username" or "username" claim cannot be empty.');
        }

        $firstName = $attributes['given_name'] ?? $attributes['firstName'] ?? null;
        if (null !== $firstName) {
            Assert::string($firstName, 'The "given_name" or "firstName" claim must be a string.');
            Assert::notEmpty($firstName, 'The "given_name" or "firstName" claim cannot be empty.');
        }

        $lastName = $attributes['family_name'] ?? $attributes['lastName'] ?? null;
        if (null !== $lastName) {
            Assert::string($lastName, 'The "family_name" or "lastName" claim must be a string.');
            Assert::notEmpty($lastName, 'The "family_name" or "lastName" claim cannot be empty.');
        }

        $roles = $attributes['roles'] ?? [];
        Assert::isArray($roles, 'The "roles" claim must be an array.');

        $roles = array_values(
            array_filter($roles, static fn (mixed $role): bool => \is_string($role) && '' !== $role),
        );

        return new User(
            id: $sub,
            email: $email,
            roles: $roles,
            userName: $userName,
            firstName: $firstName,
            lastName: $lastName
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function updateLocalUser(User $user, array $attributes): void
    {
        $needUpdate = false;

        $email = $attributes['email'] ?? null;
        if (
            \is_string($email)
            && filter_var($email, \FILTER_VALIDATE_EMAIL)
            && !empty($email)
            && $user->getEmail() !== $attributes['email']
        ) {
            $email = u($email)
                ->trim()
                ->lower()
                ->toString();

            if (!empty($email)) {
                $user->setEmail($email);
                $needUpdate = true;
            }
        }

        $userName = $attributes['username'] ?? null;
        if (
            (\is_string($userName) || is_numeric($userName))
            && !empty($userName)
            && $user->getUserName() !== $userName
        ) {
            $user->setUserName((string) $userName);
            $needUpdate = true;
        }

        $firstName = $attributes['firstName'] ?? null;
        if (
            \is_string($firstName)
            && !empty($firstName)
            && ctype_alpha($firstName)
            && $user->getFirstName() !== $firstName
        ) {
            $user->setFirstName(u($firstName)->trim()->lower()->title(true)->toString());
            $needUpdate = true;
        }

        $lastName = $attributes['lastName'] ?? null;
        if (
            \is_string($lastName)
            && !empty($lastName)
            && ctype_alpha($firstName)
            && $user->getLastName() !== $lastName
        ) {
            $user->setLastName(u($lastName)->trim()->lower()->title(true)->toString());
            $needUpdate = true;
        }

        if ($needUpdate) {
            $this->logger->info(
                \sprintf('Updating user %s with new attributes', $user->getId()),
                [
                    'attributes' => $attributes,
                ],
            );

            $this->userGateway->save($user);
        }
    }
}
