import UserPage, {IUserPageAttrs} from 'flarum/forum/components/UserPage';
import Mithril from 'mithril';
import app from 'flarum/forum/app';
import IndexPage from "flarum/forum/components/IndexPage";

export default class NewsLetterOptOutPage extends UserPage {
  private optOutEmail: string = '';
  oninit(vnode: Mithril.Vnode<IUserPageAttrs, this>) {
    super.oninit(vnode);
    this.optOutEmail = '';
  }

  view(): JSX.Element {
    const img: string = app.forum.attribute('baseUrl') + '/assets/extensions/justoverclock-newsletter/unsub.png';
    return (
      <div className="IndexPage">
        {IndexPage.prototype.hero()}
        <div className="container newsletter-opt-out-container">
          <h2>
            {app.translator.trans('justoverclock-newsletter.forum.optOutPageTitle')}
          </h2>
          <p>
            {app.translator.trans('justoverclock-newsletter.forum.optOutPageDescription')}
          </p>
          <div className='optout-container'>
            <div className='left-side-opt-out'>
              <form onsubmit={this.onsubmit.bind(this)} className='opt-out-form'>
                <input
                  onchange={(e: InputEvent) => {
                    const input = e.target as HTMLInputElement;
                    this.optOutEmail = input.value;
                  }}
                  placeholder={app.translator.trans('justoverclock-newsletter.forum.optOutPlaceholder')}
                  className='FormControl'
                  type="email"
                />
                <button
                  type='submit'
                  className='Button Button--primary newsletter-button-opt-out'
                >
                  {app.translator.trans('justoverclock-newsletter.forum.unSubscribe')}
                </button>
              </form>
            </div>
          </div>
          <div className='left-side-opt-out'>
            <img className='opt-out-img' src={img} alt="unsubscribe image"/>
          </div>
        </div>
      </div>
    );
  }

  async onsubmit(e: { preventDefault: () => void; }) {
    e.preventDefault();

    try {
      await app.request({
        method: 'DELETE',
        url: `${app.forum.attribute('apiUrl')}/newsletter/delete?email=${this.optOutEmail}`,
      })
      app.alerts.show({type: 'success'}, app.translator.trans('justoverclock-newsletter.forum.successUnsubscribedMessage'));
    } catch (e) {

    }
  }
}
