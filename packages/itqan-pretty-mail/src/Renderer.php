<?php

namespace Itqan\PrettyMail;

use Flarum\Foundation\Paths;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\HtmlString;

/**
 * Renders the admin-editable email templates that `fof/pretty-mail` used to
 * own, against Flarum 2.0's Blade based mail stack.
 *
 * Upstream `fof/pretty-mail` requires `flarum/core ^1.5` and its mailer subclass
 * (SwiftMailer, `Flarum\Api\Serializer\ForumSerializer`) no longer exists in
 * 2.0, so this is a port rather than a dependency. The stored templates and
 * their `fof-pretty-mail.*` setting keys are kept verbatim, which means an
 * existing production database keeps its branded emails without a migration.
 *
 * Templates are admin-authored and rendered as Blade, exactly like upstream.
 * They are trusted content: only accounts with the `settings` permission can
 * edit them.
 */
class Renderer
{
    private const TEMPLATE_KEYS = ['newPost', 'postMentioned', 'userMentioned'];

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Container $container,
        protected Paths $paths,
    ) {
    }

    /**
     * Render the outer HTML shell.
     *
     * @param  array<string, mixed>  $vars  Variables the mail view was given.
     */
    public function wrapper(array $vars): HtmlString
    {
        $body = $this->slot($vars, 'header')
            .$this->slot($vars, 'content')
            .$this->slot($vars, 'footer');

        // A stored notification template is a complete document; wrapping it
        // again would nest two <html> bodies.
        if ($this->isFullDocument($body)) {
            return new HtmlString($body);
        }

        $template = (string) $this->settings->get('fof-pretty-mail.mailhtml', '');

        if (trim($template) === '') {
            return new HtmlString($body);
        }

        return $this->render($template, $vars + ['body' => $body]);
    }

    /**
     * Render the body of a notification email.
     *
     * @param  array<string, mixed>  $vars  Variables the mail view was given.
     */
    public function notification(array $vars): HtmlString
    {
        $type = (string) ($vars['type'] ?? '');
        $template = '';

        if (in_array($type, self::TEMPLATE_KEYS, true)) {
            $template = (string) $this->settings->get('fof-pretty-mail.'.$type, '');
        }

        if (trim($template) !== '') {
            return $this->render($template, $vars);
        }

        // No stored override: keep core's layout, slot by slot.
        $html = '';

        if (($vars['title'] ?? null) !== null) {
            $html .= '<h2>'.e((string) $vars['title']).'</h2>';
        }

        $html .= $this->slot($vars, 'body').$this->slot($vars, 'slot');

        if (($preview = $this->slot($vars, 'preview')) !== '') {
            $html .= '<div class="content-preview">'.$preview.'</div>';
        }

        $html .= $this->slot($vars, 'footer');

        return new HtmlString($html);
    }

    /**
     * Compile and evaluate a stored Blade template.
     *
     * @param  array<string, mixed>  $vars
     */
    public function render(string $template, array $vars): HtmlString
    {
        $php = $this->container->make('blade.compiler')->compileString($template);

        $data = array_merge($vars, [
            'settings' => $this->settings,
            'url' => $this->container->make(UrlGenerator::class),
            'forumStyle' => $this->forumStyle(),
            'link' => null,
            '__env' => $this->container->make('view'),
        ]);

        unset($data['__path'], $data['__data'], $data['__file'], $data['__line']);

        $level = ob_get_level();
        ob_start();

        try {
            extract($data, EXTR_SKIP);

            eval('?>'.$php);
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $e;
        }

        return new HtmlString((string) ob_get_clean());
    }

    /**
     * The compiled forum stylesheet, when the admin asked for it to be inlined.
     */
    public function forumStyle(): string
    {
        if (! (bool) (int) $this->settings->get('fof-pretty-mail.includeCSS', '0')) {
            return '';
        }

        $candidates = glob($this->paths->public.'/assets/forum*.css') ?: [];

        if ($candidates === []) {
            return '';
        }

        usort($candidates, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return (string) @file_get_contents($candidates[0]);
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    private function slot(array $vars, string $key): string
    {
        $value = $vars[$key] ?? '';

        if ($value instanceof \Illuminate\Contracts\Support\Htmlable) {
            return (string) $value->toHtml();
        }

        return is_string($value) ? $value : (string) $value;
    }

    private function isFullDocument(string $html): bool
    {
        $head = strtolower(substr(ltrim($html), 0, 200));

        return str_starts_with($head, '<!doctype') || str_starts_with($head, '<html');
    }
}
