import Mithril from 'mithril';
import Component, { ComponentAttrs } from 'flarum/common/Component';
interface ITranslateButtonAttrs extends ComponentAttrs {
    languages: string[];
    detectedLang: string;
    onTranslate: (code: string) => void;
    iconOnly?: boolean;
}
export default class TranslateButton extends Component<ITranslateButtonAttrs> {
    view(vnode: Mithril.Vnode<ITranslateButtonAttrs>): JSX.Element | null;
}
export {};
