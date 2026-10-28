import app from 'flarum/forum/app';

/**
 * Human readable language name, resolved the same way ianm/translate did:
 * `Intl.DisplayNames`, in the current forum locale unless the admin asked for
 * native names.
 */
export default function LangDisplayName(langCode) {
  const locale = app.translator.getLocale();
  const useNative = app.forum.attribute('itqanTranslate.useNativeLocaleNames') || app.forum.attribute('ianm-translate.useNativeLocaleNames');

  if (!locale) {
    return langCode;
  }

  const displayLocale = useNative ? langCode : locale;

  if (!Intl.DisplayNames.supportedLocalesOf(displayLocale).length) {
    return langCode;
  }

  const languageNames = new Intl.DisplayNames([displayLocale], { type: 'language' });

  return languageNames.of(langCode) ?? langCode;
}
