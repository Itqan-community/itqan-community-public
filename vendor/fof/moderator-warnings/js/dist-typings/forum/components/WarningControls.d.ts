declare namespace _default {
    /**
     * Get a list of controls for a warning.
     *
     * @param {Warning} warning
     * @param {*} context The parent component under which the controls menu will
     *     be displayed.
     * @return {ItemList}
     * @public
     */
    function controls(warning: Warning, context: any): ItemList<any>;
    /**
     * Get controls for a warning pertaining to the current user (e.g. report).
     *
     * @param {Warning} warning
     * @param {*} context The parent component under which the controls menu will
     *     be displayed.
     * @return {ItemList}
     * @protected
     */
    function userControls(warning: Warning, context: any): ItemList<any>;
    /**
     * Get controls for a warning pertaining to moderation (e.g. edit).
     *
     * @param {Warning} warning
     * @param {*} context The parent component under which the controls menu will
     *     be displayed.
     * @return {ItemList}
     * @protected
     */
    function moderationControls(warning: Warning, context: any): ItemList<any>;
    /**
     * Get controls for a warning that are destructive (e.g. delete).
     *
     * @param {Warning} warning
     * @param {*} context The parent component under which the controls menu will
     *     be displayed.
     * @return {ItemList}
     * @protected
     */
    function destructiveControls(warning: Warning, context: any): ItemList<any>;
    /**
     * Hide a warning.
     *
     * @return {Promise}
     */
    function hideAction(): Promise<any>;
    /**
     * Restore a warning.
     *
     * @return {Promise}
     */
    function restoreAction(): Promise<any>;
    /**
     * Delete a warning.
     *
     * @return {Promise}
     */
    function deleteAction(context: any): Promise<any>;
}
export default _default;
import ItemList from "flarum/common/utils/ItemList";
