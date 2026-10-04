import Component from 'flarum/common/Component';
import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import icon from 'flarum/common/helpers/icon';

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

export default class PwaInstallPrompt extends Component {
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

  install() {
    const promptEvent = deferredPrompt || (typeof window !== 'undefined' ? window.deferredInstallPrompt : null);
    if (promptEvent) {
      promptEvent.prompt();
      promptEvent.userChoice.then((choiceResult) => {
        if (choiceResult.outcome === 'accepted') {
          deferredPrompt = null;
          if (typeof window !== 'undefined') window.deferredInstallPrompt = null;
        }
        app.modal.close();
      });
    }
  }

  view() {
    const installed = isAppInstalled();
    const ios = isIosSafari();
    const promptEvent = deferredPrompt || (typeof window !== 'undefined' ? window.deferredInstallPrompt : null);

    return (
      <div className="Modal modal-dialog PwaInstallModal">
        <div className="Modal-content">
          <div className="Modal-header">
            <h3 className="App-title">تثبيت تطبيق مجتمع إتقان</h3>
          </div>
          <div className="Modal-body text-center">
            {installed ? (
              <div>
                <p style="font-size: 1.1rem; margin-bottom: 1rem;">
                  {icon('fas fa-check-circle', { style: 'color: #00c853; font-size: 2.5rem; display: block; margin: 0 auto 10px;' })}
                  التطبيق مثبت بالفعل على جهازك!
                </p>
              </div>
            ) : ios ? (
              <div className="PwaInstall-ios-guide" style="padding: 10px 0;">
                <p style="font-weight: bold; margin-bottom: 15px;">لتثبيت التطبيق على جهاز آيفون / آيباد:</p>
                <ol style="text-align: start; line-height: 1.8; margin: 0 auto; max-width: 320px; font-size: 0.95rem;">
                  <li>اضغط على زر المشاركة <strong>⎋ (Share)</strong> في شريط أبل السفلي.</li>
                  <li>اسحب للأسفل واختر <strong>"إضافة إلى الشاشة الرئيسية" ➕</strong>.</li>
                  <li>اضغط على <strong>"إضافة" (Add)</strong> في أعلى الشاشة.</li>
                </ol>
              </div>
            ) : promptEvent ? (
              <div>
                <p style="margin-bottom: 20px;">استمتع بتجربة أسرع وتصفح أسهل بتثبيت التطبيق على جهازك.</p>
                <Button
                  className="Button Button--primary Button--block"
                  onclick={() => this.install()}
                  style="padding: 12px; font-size: 1rem;"
                >
                  {icon('fas fa-download', { style: 'margin-inline-end: 8px;' })}
                  تثبيت التطبيق الآن
                </Button>
              </div>
            ) : (
              <div className="PwaInstall-android-guide" style="padding: 10px 0;">
                <p style="font-weight: bold; margin-bottom: 15px;">لتثبيت التطبيق على جهاز أندرويد (Chrome / Brave):</p>
                <ol style="text-align: start; line-height: 1.8; margin: 0 auto; max-width: 320px; font-size: 0.95rem;">
                  <li>افتح قائمة المتصفح <strong>⋮ (النقاط الثلاث)</strong> في الأعلى.</li>
                  <li>اختر <strong>"إضافة إلى الشاشة الرئيسية" 📲</strong> (أو "تثبيت التطبيق").</li>
                  <li>تأكيد التثبيت بالضغط على <strong>"إضافة"</strong>.</li>
                </ol>
              </div>
            )}
          </div>
        </div>
      </div>
    );
  }
}
