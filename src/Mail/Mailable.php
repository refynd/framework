<?php

namespace Refynd\Mail;

use Refynd\Prism\PrismEngine;

/**
 * Mailable - Base class for email messages
 *
 * Provides a fluent interface for building and sending emails
 * with template rendering and attachment support.
 */
abstract class Mailable
{
    protected ?string $to = null;
    protected ?string $from = null;
    protected ?string $subject = null;
    protected ?string $template = null;
    protected array $data = [];
    protected array $attachments = [];
    protected array $headers = [];
    protected ?string $htmlContent = null;
    protected ?string $textContent = null;
    protected string $priority = 'normal';
    protected array $cc = [];
    protected array $bcc = [];
    protected ?string $replyTo = null;
    protected array $tags = [];
    protected array $metadata = [];

    /**
     * Set the recipient
     */
    public function to(string $email, ?string $name = null): self
    {
        $this->to = $name ? "{$name} <{$email}>" : $email;
        return $this;
    }

    /**
     * Set the sender
     */
    public function from(string $email, ?string $name = null): self
    {
        $this->from = $name ? "{$name} <{$email}>" : $email;
        return $this;
    }

    /**
     * Set the subject
     */
    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set the email template
     */
    public function template(string $template, array $data = []): self
    {
        $this->template = $template;
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Set template data
     */
    public function with(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Set HTML content directly
     */
    public function html(string $content): self
    {
        $this->htmlContent = $content;
        return $this;
    }

    /**
     * Set text content directly
     */
    public function text(string $content): self
    {
        $this->textContent = $content;
        return $this;
    }

    /**
     * Add CC recipient
     */
    public function cc(string $email, ?string $name = null): self
    {
        $this->cc[] = $name ? "{$name} <{$email}>" : $email;
        return $this;
    }

    /**
     * Add BCC recipient
     */
    public function bcc(string $email, ?string $name = null): self
    {
        $this->bcc[] = $name ? "{$name} <{$email}>" : $email;
        return $this;
    }

    /**
     * Set reply-to address
     */
    public function replyTo(string $email, ?string $name = null): self
    {
        $this->replyTo = $name ? "{$name} <{$email}>" : $email;
        return $this;
    }

    /**
     * Set email priority
     */
    public function priority(string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * Add file attachment
     */
    public function attach(string $path, ?string $name = null, ?string $mimeType = null): self
    {
        $this->attachments[] = ['path' => $path,
            'name' => $name ?: basename($path),
            'mime_type' => $mimeType ?: $this->guessMimeType($path),];
        return $this;
    }

    /**
     * Add attachment from data
     */
    public function attachData(string $data, string $name, string $mimeType = 'application/octet-stream'): self
    {
        $this->attachments[] = ['data' => $data,
            'name' => $name,
            'mime_type' => $mimeType,];
        return $this;
    }

    /**
     * Add custom header
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Build the mailable content
     */
    abstract public function build(): static;

    /**
     * Get the recipient
     */
    public function getTo(): ?string
    {
        return $this->to;
    }

    /**
     * Get the sender
     */
    public function getFrom(): ?string
    {
        return $this->from;
    }

    /**
     * Get the subject
     */
    public function getSubject(): ?string
    {
        return $this->subject;
    }

    /**
     * Get the rendered HTML content
     */
    public function getHtmlContent(): ?string
    {
        if ($this->htmlContent) {
            return $this->htmlContent;
        }

        if ($this->template) {
            return $this->renderTemplate($this->template . '.html');
        }

        return null;
    }

    /**
     * Get the rendered text content
     */
    public function getTextContent(): ?string
    {
        if ($this->textContent) {
            return $this->textContent;
        }

        if ($this->template) {
            return $this->renderTemplate($this->template . '.txt');
        }

        return null;
    }

    /**
     * Get CC recipients
     */
    public function getCc(): array
    {
        return $this->cc;
    }

    /**
     * Get BCC recipients
     */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    /**
     * Get reply-to address
     */
    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    /**
     * Get priority
     */
    public function getPriority(): string
    {
        return $this->priority;
    }

    /**
     * Get attachments
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * Get headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get template data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Render template content
     */
    protected function renderTemplate(string $template): ?string
    {
        try {
            // Use Prism engine if available
            if (class_exists(PrismEngine::class)) {
                $engine = new PrismEngine(
                    getcwd() . '/resources/views',
                    getcwd() . '/storage/cache/mail'
                );
                return $engine->render($template, $this->data);
            }

            // Fallback to simple PHP include
            $templatePath = getcwd() . "/resources/views/{$template}.php";
            if (file_exists($templatePath)) {
                extract($this->data);
                ob_start();
                include $templatePath;
                return ob_get_clean();
            }
        } catch (\Exception $e) {
            // Template rendering failed, return null
        }

        return null;
    }

    /**
     * Guess MIME type from file extension
     */
    protected function guessMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mimeTypes = ['txt' => 'text/plain',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Add a tag for email tracking
     */
    public function tag(string $tag): self
    {
        $this->tags[] = $tag;
        return $this;
    }

    /**
     * Add tags for email tracking
     */
    public function tags(array $tags): self
    {
        $this->tags = array_merge($this->tags, $tags);
        return $this;
    }

    /**
     * Add metadata key-value pair
     */
    public function metadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Set metadata array
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Get tags
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Get metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
