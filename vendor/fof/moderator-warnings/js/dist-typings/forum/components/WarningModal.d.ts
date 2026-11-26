export default class WarningModal extends Modal<import("flarum/common/components/Modal").IInternalModalAttrs, undefined> {
    constructor();
    oninit(vnode: any): void;
    publicComment: any;
    privateComment: any;
    strikes: any;
    content(): JSX.Element;
    onsubmit(e: any): void;
    successAlert: any;
}
import Modal from "flarum/common/components/Modal";
