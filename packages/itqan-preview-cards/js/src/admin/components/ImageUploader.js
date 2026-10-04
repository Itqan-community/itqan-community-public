import app from 'flarum/admin/app';
import Button from 'flarum/common/components/Button';
import classList from 'flarum/common/utils/classList';

export default class ImageUploader extends Button {
  loading = false;

  view(vnode) {
    const { settingKey, attribute, uploadLabel, removeLabel } = this.attrs;

    this.attrs.loading = this.loading;
    this.attrs.className = classList(this.attrs.className, 'Button');

    if (app.data.settings[settingKey]) {
      this.attrs.onclick = this.remove.bind(this);

      return (
        <div>
          <p>
            <img
              src={app.forum.attribute(attribute)}
              alt=""
              style={{ maxHeight: '140px', maxWidth: '100%', borderRadius: '6px' }}
            />
          </p>
          <p>{super.view({ ...vnode, children: removeLabel })}</p>
        </div>
      );
    }

    this.attrs.onclick = this.upload.bind(this);

    return super.view({ ...vnode, children: uploadLabel });
  }

  upload() {
    if (this.loading) return;

    const input = $('<input type="file">');

    input
      .appendTo('body')
      .hide()
      .trigger('click')
      .on('change', (e) => {
        const body = new FormData();
        body.append(this.attrs.field, e.target.files[0]);

        this.loading = true;
        m.redraw();

        app
          .request({
            method: 'POST',
            url: app.forum.attribute('apiUrl') + '/' + this.attrs.route,
            serialize: (raw) => raw,
            body,
          })
          .then(this.success.bind(this), this.failure.bind(this));
      });
  }

  remove() {
    this.loading = true;
    m.redraw();

    app
      .request({
        method: 'DELETE',
        url: app.forum.attribute('apiUrl') + '/' + this.attrs.route,
      })
      .then(this.success.bind(this), this.failure.bind(this));
  }

  success() {
    window.location.reload();
  }

  failure() {
    this.loading = false;
    m.redraw();
  }
}
