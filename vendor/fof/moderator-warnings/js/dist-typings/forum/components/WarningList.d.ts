export default class WarningList extends Component<any, undefined> {
    constructor();
    oninit(vnode: any): void;
    loading: boolean | undefined;
    warnings: any[] | undefined;
    user: any;
    view(): JSX.Element;
    actionItems(): ItemList<any>;
    strikeCount(): any;
    parseResults(results: any): any;
    refresh(): Promise<void>;
    handleOnClickCreate(e: any): void;
}
import Component from "flarum/common/Component";
import ItemList from "flarum/common/utils/ItemList";
