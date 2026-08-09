import app from 'flarum/forum/app';

export default function LangDisplayName(langCode: string): string {
  const locale = app.translator.getLocale();
  const useNative = app.forum.attribute<boolean>('ianm-translate.useNativeLocaleNames');

  if (!locale) {
    return langCode;
  }

  // Determine the locale to use for Intl.DisplayNames
  const displayLocale = useNative ? langCode : locale;

  // Check if the displayLocale is supported for Intl.DisplayNames in the current environment
  if (!Intl.DisplayNames.supportedLocalesOf(displayLocale).length) {
    return langCode;
  }

  const languageNames = new Intl.DisplayNames([displayLocale], { type: 'language' });
  return languageNames.of(langCode) ?? langCode;
}
