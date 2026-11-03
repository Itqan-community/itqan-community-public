import Modal from 'flarum/common/components/Modal';
import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import icon from '../../common/icon';

let deferredPrompt = null;

if (typeof window !== 'undefined') {
  if (window.deferredInstallPrompt) {
    deferredPrompt = window.deferredInstallPrompt;
  }
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    window.deferredInstallPrompt = e;
  });
}

export function isIosSafari() {
  if (typeof navigator === 'undefined') return false;
  const ua = navigator.userAgent || '';
  const isIos = /iPhone|iPad|iPod/.test(ua) && !window.MSStream;
  const isStandalone = ('standalone' in window.navigator) && window.navigator.standalone;
  return isIos && !isStandalone;
}

export function isAppInstalled() {
  if (typeof window === 'undefined') return false;
  return window.matchMedia('(display-mode: standalone)').matches || Boolean(window.navigator.standalone);
}

export default class PwaInstallPrompt extends Modal {
  static openModal() {
    const promptEvent = deferredPrompt || (typeof window !== 'undefined' ? window.deferredInstallPrompt : null);
    if (promptEvent) {
      promptEvent.prompt();
      promptEvent.userChoice.then((choiceResult) => {
        if (choiceResult.outcome === 'accepted') {
          deferredPrompt = null;
          if (typeof window !== 'undefined') window.deferredInstallPrompt = null;
        }
      });
      return;
    }
    app.modal.show(PwaInstallPrompt);
  }

  className() {
    return 'PwaInstallModal Modal--small';
  }

  title() {
    return app.translator.trans('itqan-theme.forum.pwa.title');
  }

  install() {
    const promptEvent = deferredPrompt || (typeof window !== 'undefined' ? window.deferredInstallPrompt : null);
    if (promptEvent) {
      promptEvent.prompt();
      promptEvent.userChoice.then((choiceResult) => {
        if (choiceResult.outcome === 'accepted') {
          deferredPrompt = null;
          if (typeof window !== 'undefined') window.deferredInstallPrompt = null;
        }
        this.hide();
      });
    }
  }

  content() {
    const installed = isAppInstalled();
    const ios = isIosSafari();
    const promptEvent = deferredPrompt || (typeof window !== 'undefined' ? window.deferredInstallPrompt : null);

    return (
      <div className="Modal-body text-center">
        {installed ? (
          <div>
            <p style="font-size: 1.1rem; margin-bottom: 1rem;">
              {icon('fas fa-check-circle', { style: 'color: #00c853; font-size: 2.5rem; display: block; margin: 0 auto 10px;' })}
              {app.translator.trans('itqan-theme.forum.pwa.installed')}
            </p>
          </div>
        ) : ios ? (
          <div className="PwaInstall-ios-guide" style="padding: 10px 0;">
            <p style="font-weight: bold; margin-bottom: 15px;">{app.translator.trans('itqan-theme.forum.pwa.ios_heading')}</p>
            <ol style="text-align: start; line-height: 1.8; margin: 0 auto; max-width: 320px; font-size: 0.95rem;">
              <li>{app.translator.trans('itqan-theme.forum.pwa.ios_step1')}</li>
              <li>{app.translator.trans('itqan-theme.forum.pwa.ios_step2')}</li>
              <li>{app.translator.trans('itqan-theme.forum.pwa.ios_step3')}</li>
            </ol>
          </div>
        ) : promptEvent ? (
          <div>
            <p style="margin-bottom: 20px;">{app.translator.trans('itqan-theme.forum.pwa.install_hint')}</p>
            <Button
              className="Button Button--primary Button--block"
              onclick={() => this.install()}
              style="padding: 12px; font-size: 1rem;"
            >
              {icon('fas fa-download', { style: 'margin-inline-end: 8px;' })}
              {app.translator.trans('itqan-theme.forum.pwa.install_now')}
            </Button>
          </div>
        ) : (
          <div className="PwaInstall-android-guide" style="padding: 10px 0;">
            <p style="font-weight: bold; margin-bottom: 15px;">{app.translator.trans('itqan-theme.forum.pwa.android_heading')}</p>
            <ol style="text-align: start; line-height: 1.8; margin: 0 auto; max-width: 320px; font-size: 0.95rem;">
              <li>{app.translator.trans('itqan-theme.forum.pwa.android_step1')}</li>
              <li>{app.translator.trans('itqan-theme.forum.pwa.android_step2')}</li>
              <li>{app.translator.trans('itqan-theme.forum.pwa.android_step3')}</li>
            </ol>
          </div>
        )}
      </div>
    );
  }
}
