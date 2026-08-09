import Modal from "flarum/common/components/Modal";
import Mithril from "mithril";
import app from 'flarum/forum/app'

export default class DataProcessingModal extends Modal {
  className(): string {
    return "newsletter-dpm";
  }

  title(): Mithril.Children {
    return 'GDPR: Data Processing';
  }

  content() {
    return (
      <div className='data-processing-modal'>
        {app.translator.trans('justoverclock-newsletter.forum.dataProcessingModalContent')}
      </div>
    )
  }
}
