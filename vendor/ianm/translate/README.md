![](https://extiverse.com/extension/ianm/translate/open-graph-image)

Translate for Flarum helps translate all your discussions without you having to do a thing. Using AI, all comments on your community can be auto-translated based on the user's language; it's as simple as clicking a button. Furthermore, the extension now supports discussion title translations.

All monthly/yearly subscription plans come with a 7-day free trial, so you can determine if this extension is right for you or not.

Technical support and updates are both included in your subscription.

## Features

- **Post Language Detection**: A handy label is displayed when the post is viewed if the viewer's locale differs from that of the post. User preference option to always show the label, if required.
- **Discussion Title Translations**: Easily detect and translate discussion titles. 
- **Post language detection** is _independent_ of any installed Forum language packs.
- **On-Demand Post Translation**: Displayed directly under the original content. Translations are cached in the database to reduce the number of translation requests to the provider, saving money (if your provider charges per translation request), and provide a speed increase.
- **Formatting Preservation**: Formatting is preserved as far as possible between the original and translated content by running translated content back through the same post renderer as the original.
- **Seamless Translations**: Users may translate from _any_ driver-supported language to _any_ enabled forum language (subject to permission). Just one language pack installed? No problem! This extension will simply translate any 'foreign' posts to your single language seamlessly.
- **Automatic Updates**: Cached translations are updated after a post has been edited.
- **Multiple Translation Driver Support**: Supports various translation sources like Google Translate, Google Cloud Translate, DeepL, and more. See `Drivers` below.
- **Translation Permissions**: Define any level of access to view translations, from `Guest` users and up, to suit your needs.
- **Admin Controls**: Admin users may force a refresh of any translation, if required.

## Screenshots

Regular permissions, English locale, Welsh content.
![image](https://user-images.githubusercontent.com/16573496/201939845-e9945776-ffab-4da6-a293-c1ded9fc6bea.png)

Regular permissions German locale, Welsh content.
![image](https://user-images.githubusercontent.com/16573496/201940062-83f33179-6f12-4633-b6fe-45f897e80f90.png)

`Translate All` permission, English locale, Welsh content.
![image](https://user-images.githubusercontent.com/16573496/201940400-bfe10283-4128-4594-9952-2fc622ecc2f1.png)

Permission options
![image](https://user-images.githubusercontent.com/16573496/202872946-19146225-f9e9-4caa-9721-28402d4bdb55.png)

User preference options

![image](https://user-images.githubusercontent.com/16573496/202012794-a3d99ea3-2388-4f3c-901c-90e1cf191b9c.png)
## Premium

This extension requires an active subscription from [extiverse](https://extiverse.com/extension/ianm/translate). Once your subscription is active you can follow the instructions on the [Extiverse subscriptions page](https://extiverse.com/premium/subscriptions) to configure composer. Once completed you can run the following command for installation:

```bash
composer require ianm/translate
```

For updates:

```bash
composer update ianm/translate
php flarum migrate
php flarum cache:clear
```

## Installation

After setting up your subscription (as described above) and installing the extension, it will be pre-configured to use the `Google Translate` driver. See [Drivers](#Drivers) for information on how to select and configure drivers.

Once the extension is enabled, any existing forum posts will not have their language detected. This means that the language header for each post will not be displayed, and the available translation options will not be as filtered as normal (i.e., normally the option to translate a post to the language it was written in is not displayed). As each post is edited, its language will be detected and will be stored and only updated if the content changes.

Alternatively, you can use the CLI to process all posts and detect their language ahead of first use. See [CLI](#CLI) below.

By default, all users (including guests) may translate post content to that of their current locale when the post content differs from it.

## Drivers

#### Google Translate
Status: Production ready. Has a fair usage limit of approx 500 translation requests per day (excluding cached translations).

This driver uses `Google Translate` APIs under the hood, but without any configuration required. Ideal for use in testing environments and small/medium sized communities.

#### Google Cloud Translate
Status: Production ready. Additional costs via Google Cloud may apply.

Setup steps:
- Log in to [Google Developer Console](https://console.developers.google.com)
- Create a new `Project` to contain your translation API, and enable any billing as required.
- Select `Enable API` and search for `Cloud Translation API` and enable it.
- Click `Create Credentials` and choose `API Key`. Give this key a name so you can refer back to it at a later date. Optionally, restrict this key to the translation API only for improved security.
- Once you have generated your key, copy/paste it into the `Google Cloud Translator` section of this extension's settings.

#### DeepL
Status: Beta. Additional costs via DeepL may apply, depending on your chosen plan.

Supports both the DeepL free and Pro variants, formality setting and English "flavour" (en-US or en-GB)

Setup steps:
- Log in and choose a plan at [DeepL](https://www.deepl.com/pro#developer)
- Follow the signup steps as described. Note, you will need to enter billing information, even for the free teir.
- Once you have setup your new account, obtain your API key from [DeepL Account Settings](https://www.deepl.com/account/summary) and enter it into the relevant setting field in this extension.
- [optional] set your preferences for `Formality` and `English variant`

#### LibreTranslate
Status: experimental/under development

#### AWS Translate
Status: planned

#### Azure Cognitive Services Translator
Status: planned

#### Yandex Translate
Status: planned

## Translation strings

While user-generated content is handled by your chosen translation driver, some of the UI elements of this extension rely on traditional Flarum language packs. Check on the status of your language on [weblate](https://weblate.rob006.net/projects/flarum/ianm-translate/)

## CLI

This extension provides several command-line interfaces (CLI) to detect and handle the languages of discussions and posts. Here are the commands, their options, and a brief description:


### `translate:detect`


This command detects the language of both discussions and posts that do not have a detected language set.
#### Options

    --force: Force all items (discussions/posts) to be re-detected.

Example:

```bash
php flarum translate:detect

php flarum translate:detect --force
```
### `translate:detect-discussions`

Detects the language of all discussions that do not have a detected language set.
#### Options

    --discussion=ID: Specify the discussion ID to detect languages. Replace ID with the appropriate discussion ID.
    --force: Force all discussions to be re-detected.

Examples:

```bash

php flarum translate:detect-discussions --discussion=26
php flarum translate:detect-discussions --force
php flarum translate:detect-discussions --discussion=26 --force
```
### `translate:detect-posts`

Detects the language of all posts that do not have a detected language set.
#### Options

    --post=ID: Specify the post ID to detect languages of posts. Replace ID with the appropriate post ID.
    --force: Force all posts to be re-detected.

Examples:

```bash

php flarum translate:detect-posts --post=15
php flarum translate:detect-posts --force
php flarum translate:detect-posts --post=15 --force
```
You should ensure that all discussions/posts have had their languages identified, so that the correct translation experience is presented to your forum users.

## FAQs

#### How are translation updates handled?

When a post is edited, all cached translations are marked as "needing an update". Rather than automatically refreshing _all_ translations for that post, the next time a translation for `x` language is requested, we call the translation service to provide it seamlessly on demand. This helps to reduce translation costs and server load too.

## Future improvements/additions

In addition to the stated additional translation provider drivers, other features may be added over time. These will be driven by subscriber feedback. Accepted and planned improvements will appear here.

## Support

Technical support is included in your subscription and is provided in the first instance on [Discuss](https://discuss.flarum.org/d/32007), but can also be provided via [email](mailto:translate@morland.dev), Discord, etc., as required.


## Links

- [Extiverse](https://extiverse.com/extension/ianm/translate)
- [Discuss](https://discuss.flarum.org/d/32007)
