// Build the JSON:API payload for a reply posted from the in-card form.
export function buildReplyData(content, targetId, discussion) {
  return {
    content: String(content == null ? '' : content),
    replyToPostId: Number(targetId),
    relationships: { discussion },
  };
}
