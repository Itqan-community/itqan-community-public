import Component, { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
interface ITranslationSourceLabelAttrs extends ComponentAttrs {
    from: string;
    to: string;
}
export default class TranslationSourceLabel extends Component<ITranslationSourceLabelAttrs> {
    view(vnode: Mithril.VnodeDOM<ITranslationSourceLabelAttrs>): Mithril.Children;
}
export {};
