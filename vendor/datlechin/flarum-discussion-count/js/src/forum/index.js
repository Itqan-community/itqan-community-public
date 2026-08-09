import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import TagLinkButton from 'flarum/tags/components/TagLinkButton';
import TagsPage from 'flarum/tags/components/TagsPage';
import classList from 'flarum/common/utils/classList';
import tagIcon from 'flarum/tags/common/helpers/tagIcon';
import Link from 'flarum/common/components/Link';
import sortTags from 'flarum/tags/common/utils/sortTags';

app.initializers.add('datlechin/flarum-discussion-count', () => {
  override(TagLinkButton.prototype, 'view', function () {
    const tag = this.attrs.model;
    const description = tag && tag.description();
    const className = classList(['TagLinkButton', 'hasIcon', this.attrs.className, tag.isChild() && 'child']);

    return (
      <Link className={className} href={this.attrs.route} style={tag ? { '--color': tag.color() } : ''} title={description || ''}>
        {tagIcon(tag, { className: 'Button-icon' })}
        <span className="Button-label">
          {tag ? tag.name() : app.translator.trans('flarum-tags.forum.index.untagged_link')} ({tag.discussionCount()})
        </span>
      </Link>
    );
  });

  extend(TagsPage.prototype, ['oncreate'], function () {
    const tags = this.tags;
    const tagsItem = document.querySelectorAll('.TagTile-info');

    tagsItem.forEach(function (item) {
      const tagName = item.querySelector('.TagTile-name').innerText;
      const tag = tags.find((tag) => tag.name() === tagName);
      const children = sortTags(tag.children() || []);
      const discussionCount = tag.discussionCount();

      const childrenLinks = item.querySelectorAll('.TagTile-children a');

      childrenLinks.forEach(function (link) {
        const childTagName = link.innerText;
        const childTag = children.find((tag) => tag.name() === childTagName);
        const childDiscussionCount = childTag.discussionCount();
        link.innerText = `${childTagName} (${childDiscussionCount})`;
      });

      item.querySelector('.TagTile-name').innerText += ` (${discussionCount})`;
    });
  });
});
