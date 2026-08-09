import Component, {ComponentAttrs} from "flarum/common/Component";
import Mithril from 'mithril'
import Switch from 'flarum/common/components/Switch'
import app from 'flarum/forum/app'
import DataProcessingModal from "./Modals/DataProcessingModal";

export default class NewsLetterBox extends Component {
  private emailRegex: RegExp = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  private isEmailValid: boolean = true;
  agree: boolean = false;
  private email: string = '';
  oninit(vnode: Mithril.Vnode<ComponentAttrs, this>) {
    super.oninit(vnode);
    this.agree = false;
    this.isEmailValid = true;
    this.email = "";
  }

  onFieldChange(value: string) {
    return this.emailRegex.test(value);
  }

  openModal() {
    app.modal.show(DataProcessingModal)
  }

  view(vnode: Mithril.Vnode<ComponentAttrs, this>): Mithril.Children {
    return (
      <div class='newsletter-optin-container'>
        <h3 className='newsletter-title'>
          {app.translator.trans('justoverclock-newsletter.forum.newsLetterTitle')}
        </h3>
        <p className='newsletter-description'>
          {app.translator.trans('justoverclock-newsletter.forum.newsLetterDescription')}
        </p>
        <form onsubmit={this.onsubmit.bind(this)}>
          <input
            onchange={(e: InputEvent) => {
              const input = e.target as HTMLInputElement;
              this.email = input.value;
              this.isEmailValid = this.onFieldChange(this.email);
            }}
            className='FormControl'
            placeholder='email@example.com'
            type="email"
          />
          {!this.isEmailValid && (
            <div>
              <small className="hint-text" style={{color: "red"}}>
                {app.translator.trans("justoverclock-newsletter.forum.invalidEmailHint")}
              </small>
            </div>
          )}
          <a onclick={this.openModal}>
            {app.translator.trans('justoverclock-newsletter.forum.newsLetterModalTitle')}
          </a>
          <Switch
            className='newsletter-agree'
            state={this.agree}
            onchange={(val: boolean): boolean => this.agree = val}
          >
            <span className='newsletter-agree-label'>
              {app.translator.trans('justoverclock-newsletter.forum.consent')}
            </span>
          </Switch>
          <button
            disabled={!this.agree || !this.onFieldChange(this.email)}
            type='submit'
            className='Button Button--primary newsletter-button'
          >
            {app.translator.trans('justoverclock-newsletter.forum.subscribe')}
          </button>
        </form>
      </div>
    );
  }

  async onsubmit(e: { preventDefault: () => void; }) {
    e.preventDefault();
    try {
      await app.request({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/newsletter/add`,
        body: {
          email: this.email
        }
      })
      app.alerts.show({ type: 'success' }, app.translator.trans('justoverclock-newsletter.forum.successMessage'));
    } catch (e) {
      console.error(e)
    }
    m.redraw();
  }
}
