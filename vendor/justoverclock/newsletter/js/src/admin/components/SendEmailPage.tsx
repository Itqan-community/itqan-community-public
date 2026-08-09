// @ts-nocheck
import ExtensionPage, {ExtensionPageAttrs} from "flarum/admin/components/ExtensionPage";
import Mithril from "mithril";
import app from 'flarum/admin/app'
import Quill from 'quill';

export default class SendEmailPage extends ExtensionPage {
  oninit(vnode: Mithril.Vnode<ExtensionPageAttrs, this>) {
    super.oninit(vnode);
    this.loading = false
    this.getLastNewsletterDetails()
    this.getTotalSubscribers()
    this.lastNewsletterdetail = null
    this.totalSubscribers = 0;
    this.toolbarOptions = [
      [{ font: [] }],
      [{ header: [1, 2, 3] }],
      ["bold", "italic", "underline", "strike"],
      [{ color: [] }, { background: [] }],
      [{ list: "ordered" }, { list: "bullet" }, { list: "check" }],
      ["blockquote", "code-block"],
      ["link"],
      [{ align: [] }],
    ];
    console.log(this.lastNewsletterdetail)
  }

  oncreate(vnode: Mithril.VnodeDOM<ExtensionPageAttrs, this>) {
    super.oncreate(vnode);


    this.quill = new Quill("#editor-container", {
      theme: "snow",
      modules: {
        toolbar: this.toolbarOptions,
      },
    });

    this.quill.on("text-change", () => {
      this.body = this.quill.getSemanticHTML();
      m.redraw();
    });
  }

  content(vnode: Mithril.VnodeDOM<ExtensionPageAttrs, this>): JSX.Element {
    return (
      <div className="container">
        <div className='newsletter-stats-container'>
          <div className='newsletter-stats-card'>
            <h3>{app.translator.trans('justoverclock-newsletter.admin.totalSubscribers')}</h3>
            <small>{app.translator.trans('justoverclock-newsletter.admin.totalSubscribersCount')}</small>
            <h2 className='stats-number'>{this.totalSubscribers}</h2>
            <p>
              {app.translator.trans('justoverclock-newsletter.admin.newsletterCountText')}
            </p>
          </div>
          <div className='newsletter-stats-card'>
            <h3>{app.translator.trans('justoverclock-newsletter.admin.lastNewsletterSent')}</h3>
            <small>{app.translator.trans('justoverclock-newsletter.admin.lastNewsletterSentDescription')}</small>
            <h2 className='stats-number'>
              {this.lastNewsletterdetail
                ? this.lastNewsletterdetail.attributes.title
                : app.translator.trans('justoverclock-newsletter.admin.noNewsletterSent')}
            </h2>
            {this.lastNewsletterdetail
              ? `${app.translator.trans('justoverclock-newsletter.admin.lastNewsletterSentText')} ${new Date(this.lastNewsletterdetail.attributes.createdAt).toISOString().slice(0,16)}`
              : app.translator.trans('justoverclock-newsletter.admin.noNewsletterSent')}
          </div>
        </div>
        <div className="Form-group">
          <label>{app.translator.trans('justoverclock-newsletter.admin.emailTitle')}</label>
          <input
            className="FormControl"
            type="text"
            placeholder="newsletter title"
            oninput={(e: { target: { value: any; }; }) => (this.subject = e.target.value)}
          />
        </div>
        <div className="Form-group">
          <label>{app.translator.trans('justoverclock-newsletter.admin.textOrHtml')}</label>
          <div className='editor-container FormControl' id='editor-container'></div>
        </div>
        <button
          className="Button Button--primary"
          onclick={this.sendEmail.bind(this)}
          disabled={!this.subject || !this.body}
        >
          {this.loading ? `${app.translator.trans('justoverclock-newsletter.admin.sendingText')}` : `${app.translator.trans('justoverclock-newsletter.admin.sendEmailButtonText')}`}
        </button>
      </div>
    )
  }

  getTotalSubscribers() {
    app.request({
      method: 'GET',
      url: app.forum.attribute('apiUrl') + '/newsletter/subscribers',
    }).then(data => {
      this.totalSubscribers = data.data.length
      m.redraw()
    })
  }

  getLastNewsletterDetails() {
    app.request({
      method: 'GET',
      url: app.forum.attribute('apiUrl') + '/last-newsletter/get',
    }).then(data => {
      this.lastNewsletterdetail = data.data.length > 0 ? data.data[0] : null;
      console.log(data.data)
      m.redraw()
    })
  }

  sendEmail() {
    this.loading = true
    this.body = this.quill.getSemanticHTML();
    app.request({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/newsletter/sendall',
      body: {
        email: this.email,
        subject: this.subject,
        body: this.body,
        html: this.html,
      },
    })
      .then(() => {
        this.loading = false
        app.alerts.show({type: 'success'}, `${app.translator.trans('justoverclock-newsletter.admin.emailSentSuccessMessage')}`);
      })
      .catch(() => {
        app.alerts.show({type: 'error'}, `${app.translator.trans('justoverclock-newsletter.admin.emailSentErrorMessage')}`);
      });
  }
}
