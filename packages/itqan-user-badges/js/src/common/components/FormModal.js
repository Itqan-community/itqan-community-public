import Modal from 'flarum/common/components/Modal';

/**
 * Flarum 1.x wrapped every modal in a `<form>`, so components could rely on a
 * `type="submit"` button and an `onsubmit` method. Flarum 2's `Modal` renders a
 * plain `<div>`, and without the form the submit button does nothing (a native
 * submit navigates away). This restores the 1.x behaviour for the ported modals.
 */
export default class FormModal extends Modal {
  wrapper(children) {
    return <form onsubmit={(e) => this.onsubmit(e)}>{children}</form>;
  }
}
