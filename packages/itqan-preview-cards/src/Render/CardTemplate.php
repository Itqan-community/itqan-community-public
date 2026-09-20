<?php

namespace Itqan\PreviewCards\Render;

use Itqan\PreviewCards\Card\CardData;

class CardTemplate
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;

    private const EN_SIZES = [43, 39, 35];
    private const AR_SIZES = [39, 35, 31];

    private const ICON_CALENDAR = '<svg class="icon" viewBox="0 0 48 48" fill="none" aria-hidden="true"><rect x="7" y="10" width="34" height="31" rx="4" stroke="currentColor" stroke-width="3"/><path d="M14 6v9M34 6v9M8 20h32" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>';
    private const ICON_BUBBLE = '<svg class="icon" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M40 22.5c0 9.1-7.3 16.5-16.4 16.5-2.8 0-5.4-.7-7.7-1.9L8 40l2.6-7.6A16.4 16.4 0 0 1 7 22.5C7 13.4 14.4 6 23.6 6S40 13.4 40 22.5Z" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/></svg>';
    private const ICON_CLOCK = '<svg class="icon" viewBox="0 0 48 48" fill="none" aria-hidden="true"><circle cx="24" cy="24" r="17" stroke="currentColor" stroke-width="3"/><path d="M24 14v11l7 5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    public function __construct(protected FontSet $fonts)
    {
    }

    public function forDiscussion(CardData $data): string
    {
        $elements = $data->elements;
        $colors = $data->colors;
        $lang = $data->locale === 'ar' ? 'ar' : 'en';
        $dir = $data->direction === 'rtl' ? 'rtl' : 'ltr';
        $fonts = $this->fonts->inlineCss();
        $sizes = json_encode($lang === 'ar' ? self::AR_SIZES : self::EN_SIZES);

        $bg = $this->hex($colors['background'] ?? '', '#004638');
        $accent = $this->hex($colors['accent'] ?? '', '#00ad83');
        $titleColor = $this->hex($colors['title'] ?? '', '#ffffff');
        $textColor = $this->hex($colors['text'] ?? '', '#cbd7d4');
        $metaColor = $this->hex($colors['meta'] ?? '', '#d7e1de');
        $tagColor = $this->hex($colors['tag'] ?? '', '#c2d2ce');

        $bgStart = $this->shade($bg, 0.86);
        $bgEnd = $this->shade($bg, 0.94);
        $accentSoft = $this->rgba($accent, 0.22);
        $bgGlow = $this->rgba($this->shade($bg, 0.62), 0.38);
        $motif = $this->rgba($accent, 0.55);
        $railTop = $this->rgba($accent, 0.38);
        $railBottom = $this->rgba($accent, 0.28);
        $divider = $this->rgba($this->shade($accent, 0.85), 0.55);
        $accentDark = $this->shade($accent, 0.72);
        $accentDeep = $this->shade($accent, 0.55);
        $tagBorder = $this->rgba($tagColor, 0.35);

        $site = $this->escape($data->siteTitle);
        $title = $this->escape($data->title);

        $brandBlock = '';

        if (($elements['logo'] ?? false) && $data->logoDataUri !== null) {
            $brandBlock = '<img src="'.$this->escape($data->logoDataUri).'" alt="">';
        } elseif (($elements['brand'] ?? false) && $site !== '') {
            $brandBlock = '<span class="brand">'.$site.'</span>';
        }

        $brandBox = $brandBlock !== '' ? '<div class="logo-box">'.$brandBlock.'</div>' : '';

        $taglineBlock = ($elements['tagline'] ?? false) && $data->tagline !== null
            ? '<div class="tagline">'.$this->escape($data->tagline).'</div>'
            : '';

        $tagBlock = ($elements['tag'] ?? false) && $data->tagName !== null
            ? '<span class="tag">'.$this->escape($data->tagName).'</span>'
            : '';

        $descriptionBlock = ($elements['excerpt'] ?? false) && $data->excerpt !== null
            ? '<div class="description">'.$this->escape($data->excerpt).'</div>'
            : '';

        $metaBlock = $this->metaBlock($data, $lang, $elements, $titleColor);

        $background = $this->backgroundLayers($data->backgroundDataUri, $bg);

        return <<<HTML
<!doctype html>
<html lang="{$lang}" dir="{$dir}">
<head>
<meta charset="utf-8">
<style>
{$fonts}
*{margin:0;padding:0;box-sizing:border-box}
html,body{width:1200px;height:630px;overflow:hidden}
body{font-family:'Itqan Cards',system-ui,-apple-system,'Segoe UI',sans-serif;-webkit-font-smoothing:antialiased}
.frame{position:relative;width:1200px;height:630px;overflow:hidden;color:{$titleColor};background:radial-gradient(circle at 65% 14%,{$accentSoft},transparent 35%),radial-gradient(circle at 38% 70%,{$bgGlow},transparent 42%),linear-gradient(120deg,{$bgStart} 0%,{$bg} 52%,{$bgEnd} 100%)}
.frame::after{content:'';position:absolute;inset:0;pointer-events:none;background:linear-gradient(90deg,rgba(0,0,0,.04),rgba(0,0,0,0) 45%,rgba(255,255,255,.01))}
.bg{position:absolute;inset:0;background-size:cover;background-position:center;background-repeat:no-repeat}
.shade{position:absolute;inset:0}
.motif-top,.motif-bottom{position:absolute;inset-inline-end:-41px;border:50px solid {$motif};border-right-color:transparent;border-bottom-color:transparent;border-radius:90px 0 0 0;transform:rotate(-45deg);z-index:1}
.motif-top{top:5px;width:259px;height:259px}
.motif-bottom{bottom:-105px;width:285px;height:285px;opacity:.92}
.rail{position:absolute;inset-inline-end:146px;top:143px;width:60px;height:420px;background:linear-gradient(180deg,{$railTop},{$railBottom});border-radius:0 0 5px 5px;z-index:1}
.diamond{position:absolute;inset-inline-end:168px;top:59px;width:84px;height:84px;transform:rotate(45deg);background:linear-gradient(135deg,{$accent},{$accentDark});opacity:.78;z-index:1}
.logo-box{position:absolute;top:44px;inset-inline-start:68px;width:188px;height:143px;overflow:hidden;display:flex;align-items:center;justify-content:flex-start;z-index:2}
.logo-box img{width:100%;height:100%;object-fit:contain;display:block}
.brand{font-size:34px;font-weight:700;color:{$titleColor}}
.accent{position:absolute;top:185px;inset-inline-start:83px;width:63px;height:4px;background:{$accent};z-index:2}
.tagline{position:absolute;top:206px;inset-inline-start:80px;width:420px;text-align:start;font-size:22px;line-height:1.4;font-weight:400;color:{$textColor};display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;z-index:2}
.tag{position:absolute;top:44px;inset-inline-end:79px;font-size:20px;font-weight:400;color:{$tagColor};border:2px solid {$tagBorder};border-radius:999px;padding:7px 18px;white-space:nowrap;z-index:2}
.title{position:absolute;top:278px;inset-inline-start:81px;width:953px;text-align:start;font-size:39px;line-height:1.35;font-weight:700;letter-spacing:-.19px;color:{$titleColor};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;z-index:2}
html[lang="ar"] .title{font-size:39px}
html[lang="en"] .title{font-size:43px}
.description{position:absolute;top:365px;inset-inline-start:81px;width:990px;text-align:start;font-size:23px;line-height:1.65;font-weight:400;color:{$textColor};display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;overflow-wrap:break-word;z-index:2}
.divider{position:absolute;inset-inline:79px;top:506px;height:2px;background:{$divider};z-index:2}
.meta{position:absolute;bottom:54px;inset-inline:79px;display:flex;direction:ltr;align-items:center;justify-content:space-between;color:{$metaColor};z-index:2}
.meta-items{display:flex;direction:ltr;align-items:center;gap:20px;font-size:21px}
.meta-item{display:flex;direction:ltr;align-items:center;gap:12px}
html[dir="rtl"] .meta-item{direction:rtl}
html[dir="ltr"] .meta,html[dir="ltr"] .meta-items{flex-direction:row-reverse}
.icon{width:29px;height:29px;display:block;flex:0 0 auto}
.bullet{width:6px;height:6px;border-radius:50%;background:currentColor;opacity:.8;flex:0 0 auto}
.admin{display:flex;direction:ltr;align-items:center;gap:17px;font-size:22px;font-weight:700;color:{$titleColor}}
html[dir="ltr"] .admin{flex-direction:row-reverse}
.admin-name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px}
.avatar{width:58px;height:58px;border-radius:50%;background:conic-gradient(from 20deg,{$accent},{$accentDark},{$accentDeep},{$accent});display:grid;place-items:center;overflow:hidden;flex:0 0 auto}
.avatar img{width:100%;height:100%;object-fit:cover;display:block}
.avatar-inner{width:32px;height:32px;border-radius:50%;background:#f7f7f7;color:{$bg};display:grid;place-items:center;font-size:19px;font-weight:700}
</style>
</head>
<body>
{$background}
<main class="frame" role="img" aria-label="{$title}">
<div class="motif-top" aria-hidden="true"></div>
<div class="motif-bottom" aria-hidden="true"></div>
<div class="rail" aria-hidden="true"></div>
<div class="diamond" aria-hidden="true"></div>
{$brandBox}
<div class="accent" aria-hidden="true"></div>
{$taglineBlock}
{$tagBlock}
<h1 class="title">{$title}</h1>
{$descriptionBlock}
<div class="divider" aria-hidden="true"></div>
{$metaBlock}
</main>
<script>
(async function () {
    try {
        if (document.fonts && document.fonts.ready) { await document.fonts.ready; }
        var sizes = {$sizes};
        var title = document.querySelector('.title');
        if (!title) { return; }
        for (var s = 0; s < sizes.length; s++) {
            title.style.fontSize = sizes[s] + 'px';
            if (title.scrollWidth <= title.clientWidth + 1) {
                document.documentElement.dataset.fit = 'ok';
                return;
            }
        }
        document.documentElement.dataset.fit = 'overflow';
    } catch (e) {
        document.documentElement.dataset.fit = 'error';
    }
})();
</script>
</body>
</html>
HTML;
    }

    public function forBrand(
        string $locale,
        string $siteTitle,
        string $tagline,
        array $colors,
        string $domain,
        ?string $logoDataUri = null,
        ?string $backgroundDataUri = null,
    ): string {
        $lang = $locale === 'ar' ? 'ar' : 'en';
        $dir = $lang === 'ar' ? 'rtl' : 'ltr';
        $fonts = $this->fonts->inlineCss();

        $bg = $this->hex($colors['background'] ?? '', '#004638');
        $accent = $this->hex($colors['accent'] ?? '', '#00ad83');
        $titleColor = $this->hex($colors['title'] ?? '', '#ffffff');
        $textColor = $this->rgba($colors['text'] ?? '', 0.85);
        $domainColor = $this->rgba($colors['title'] ?? '', 0.85);
        $shadeTop = $this->rgba($colors['background'] ?? '', 0.80);
        $shadeBottom = $this->rgba($colors['background'] ?? '', 0.94);

        $site = $this->escape($siteTitle);
        $tagline = $this->escape($tagline);
        $domain = $this->escape($domain);
        $brand = $logoDataUri !== null
            ? '<img class="logo" src="'.$this->escape($logoDataUri).'" alt="">'
            : '<h1 class="site">'.$site.'</h1>';
        $background = $this->backgroundLayers($backgroundDataUri, $bg);

        return <<<HTML
<!doctype html>
<html lang="{$lang}" dir="{$dir}">
<head>
<meta charset="utf-8">
<style>
{$fonts}
*{margin:0;padding:0;box-sizing:border-box}
html,body{width:1200px;height:630px;overflow:hidden}
body{font-family:'Itqan Cards',system-ui,-apple-system,'Segoe UI',sans-serif;background:{$bg};color:{$titleColor}}
.bg{position:absolute;inset:0;background-size:cover;background-position:center;background-repeat:no-repeat}
.shade{position:absolute;inset:0;background:linear-gradient(180deg,{$shadeTop},{$shadeBottom})}
.card{position:relative;z-index:1;width:1200px;height:630px;padding:96px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:34px}
.card::before{content:'';position:absolute;top:0;inset-inline-start:0;width:100%;height:10px;background:{$accent}}
.rule{width:96px;height:5px;border-radius:3px;background:{$accent}}
.logo{height:120px;width:auto;max-width:640px;object-fit:contain;display:block}
.site{font-size:84px;font-weight:700;line-height:1.2;color:{$titleColor}}
html[lang="ar"] .site{font-size:76px}
.tagline{font-size:32px;line-height:1.5;color:{$textColor};max-width:820px}
.domain{font-size:26px;font-weight:700;color:{$domainColor};position:absolute;bottom:64px;inset-inline-end:96px}
</style>
</head>
<body>
{$background}
<main class="card">
<div class="rule"></div>
{$brand}
<p class="tagline">{$tagline}</p>
<span class="domain">{$domain}</span>
</main>
</body>
</html>
HTML;
    }

    public function repliesLabel(int $replies, string $locale): string
    {
        if ($locale === 'ar') {
            return match (true) {
                $replies <= 0 => 'لا ردود بعد',
                $replies === 1 => 'رد واحد',
                $replies === 2 => 'ردان',
                $replies <= 10 => $replies.' ردود',
                default => $replies.' ردًا',
            };
        }

        return match (true) {
            $replies <= 0 => 'No replies yet',
            $replies === 1 => '1 reply',
            default => $replies.' replies',
        };
    }

    public function lastReplyLabel(string $date, string $locale): string
    {
        return $locale === 'ar' ? 'آخر رد '.$date : 'Last reply '.$date;
    }

    private function metaBlock(CardData $data, string $lang, array $elements, string $titleColor): string
    {
        $items = [];

        if (($elements['date'] ?? false) && $data->displayDate !== '') {
            $items[] = $this->metaItem(self::ICON_CALENDAR, $data->displayDate);
        }

        if ($elements['replies'] ?? false) {
            $items[] = $this->metaItem(self::ICON_BUBBLE, $this->repliesLabel($data->replyCount, $lang));
        }

        if (($elements['last_reply'] ?? false) && $data->lastReplyDisplay !== null && $data->lastReplyDisplay !== '') {
            $items[] = $this->metaItem(self::ICON_CLOCK, $this->lastReplyLabel($data->lastReplyDisplay, $lang));
        }

        $admin = '';
        $name = (string) ($data->authorName ?? '');

        if (($elements['author'] ?? false) && $name !== '') {
            $admin .= '<span class="admin-name">'.$this->escape($name).'</span>';
        }

        if (($elements['avatar'] ?? false) && $name !== '') {
            $admin .= $this->avatar($data, $name);
        }

        if ($admin !== '') {
            $admin = '<div class="admin">'.$admin.'</div>';
        }

        if (empty($items) && $admin === '') {
            return '';
        }

        return '<div class="meta"><div class="meta-items">'.implode('<span class="bullet"></span>', $items).'</div>'.$admin.'</div>';
    }

    private function metaItem(string $icon, string $text): string
    {
        return '<div class="meta-item">'.$icon.'<span>'.$this->escape($text).'</span></div>';
    }

    private function backgroundLayers(?string $dataUri, string $bg): string
    {
        if ($dataUri === null || $dataUri === '') {
            return '';
        }

        $shade = 'linear-gradient(180deg,'.$this->rgba($bg, 0.78).','.$this->rgba($bg, 0.93).')';

        return '<div class="bg" style="background-image:url(\''.$this->escape($dataUri).'\')"></div>'
            .'<div class="shade" style="background:'.$shade.'"></div>';
    }

    private function avatar(CardData $data, string $name): string
    {
        if ($data->avatarDataUri !== null) {
            return '<span class="avatar"><img src="'.$this->escape($data->avatarDataUri).'" alt=""></span>';
        }

        $initial = mb_substr(trim($name), 0, 1, 'UTF-8');

        return '<span class="avatar"><span class="avatar-inner">'.$this->escape($initial).'</span></span>';
    }

    private function hex(string $color, string $default): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : $default;
    }

    private function shade(string $color, float $factor): string
    {
        $hex = ltrim($this->hex($color, '#000000'), '#');
        $parts = sscanf($hex, '%02x%02x%02x');
        $factor = max(0.0, $factor);

        $channel = function (int $value) use ($factor): int {
            return max(0, min(255, (int) round($value * $factor)));
        };

        return sprintf(
            '#%02x%02x%02x',
            $channel($parts[0] ?? 0),
            $channel($parts[1] ?? 0),
            $channel($parts[2] ?? 0),
        );
    }

    private function rgba(string $color, float $alpha): string
    {
        $hex = ltrim($this->hex($color, '#ffffff'), '#');
        $parts = sscanf($hex, '%02x%02x%02x');

        return 'rgba('.($parts[0] ?? 255).','.($parts[1] ?? 255).','.($parts[2] ?? 255).','.$alpha.')';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
