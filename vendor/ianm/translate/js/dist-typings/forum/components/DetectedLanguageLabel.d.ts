import Mithril, { Vnode } from 'mithril';
import Component from 'flarum/common/Component';
export interface IDetectedLanguageLabelAttrs {
    detectedLang: string;
    context: 'post' | 'discussion';
}
export default class DetectedLanguageLabel extends Component<IDetectedLanguageLabelAttrs> {
    view(vnode: Vnode<IDetectedLanguageLabelAttrs>): Mithril.Children;
}
