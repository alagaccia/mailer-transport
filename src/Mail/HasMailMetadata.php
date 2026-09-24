<?php

namespace AndreaLagaccia\MailerTransport\Mail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Metadata a Mailable declares in its envelope so that a MessageSent
 * listener can log the email with its type and the model it is about.
 *
 * Every metadata key travels as an `X-Metadata-{key}` header; read them with
 * `$message->getHeaders()->get('X-Metadata-email_type')` and so on. The
 * `sync` key is also read by ApiTransport (SYNC_HEADER) to ask the mailer
 * for an immediate delivery of this type of email.
 */
trait HasMailMetadata
{
    private ?Model $emailableModel = null;

    /**
     * Sets the model this email is about (an order, a booking, a report…),
     * to be stored as a polymorphic relation of the log row.
     */
    public function withEmailable(Model $model): static
    {
        $this->emailableModel = $model;

        return $this;
    }

    /**
     * The model this email is about: the one given to withEmailable(),
     * otherwise the first Eloquent model received by the constructor that is
     * not excluded by emailableExcludedClasses().
     */
    public function resolveEmailable(): ?Model
    {
        if ($this->emailableModel !== null) {
            return $this->emailableModel;
        }

        $constructor = (new ReflectionClass($this))->getConstructor();

        if ($constructor === null) {
            return null;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $name = $parameter->getName();

            if (! isset($this->$name) || ! $this->$name instanceof Model) {
                continue;
            }

            if ($this->isExcludedEmailable($this->$name)) {
                continue;
            }

            $this->emailableModel = $this->$name;

            return $this->emailableModel;
        }

        return null;
    }

    /**
     * Model classes never used as emailable (typically the ones with a
     * dedicated column on the log table, such as the recipient user).
     *
     * @return list<class-string<Model>>
     */
    protected function emailableExcludedClasses(): array
    {
        return [];
    }

    /**
     * Code of the email type: the `$emailTypeCode` property when declared,
     * otherwise the snake case of the class name without "Mail"
     * (UserInvitedMail => user_invited).
     */
    public function resolveEmailTypeCode(): string
    {
        if (property_exists($this, 'emailTypeCode') && ! empty($this->emailTypeCode)) {
            return $this->emailTypeCode;
        }

        return Str::snake(Str::beforeLast(class_basename($this), 'Mail'));
    }

    /**
     * Whether the mailer must deliver this email synchronously: the
     * `$emailSync` property when declared, otherwise null (the `sync` of the
     * mailer configuration applies).
     */
    public function resolveEmailSync(): ?bool
    {
        if (property_exists($this, 'emailSync') && is_bool($this->emailSync)) {
            return $this->emailSync;
        }

        return null;
    }

    /**
     * Adds `email_type`, `emailable_type`, `emailable_id` and, when declared,
     * `sync` to the given metadata, without overriding keys already set.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    protected function mergeMailMetadata(array $metadata = []): array
    {
        $metadata['email_type'] ??= $this->resolveEmailTypeCode();

        $sync = $this->resolveEmailSync();

        if ($sync !== null) {
            $metadata['sync'] ??= $sync ? 'true' : 'false';
        }

        $emailable = $this->resolveEmailable();

        if ($emailable !== null) {
            $metadata['emailable_type'] ??= $emailable->getMorphClass();
            $metadata['emailable_id'] ??= $emailable->getKey();
        }

        return $metadata;
    }

    private function isExcludedEmailable(Model $model): bool
    {
        foreach ($this->emailableExcludedClasses() as $class) {
            if ($model instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
