import { Editor, Node, type JSONContent } from '@tiptap/core';
import Collaboration from '@tiptap/extension-collaboration';
import type { Transaction } from '@tiptap/pm/state';
import StarterKit from '@tiptap/starter-kit';
import DOMPurify from 'dompurify';
import type * as Y from 'yjs';

export const DOCUMENT_VERSION = 'peanut.richtext/1' as const;

export type CommentAnchor = {
  id: string;
  from: number;
  to: number;
  quote: string;
  status: 'active' | 'invalid';
};

export type CurrentDocument = {
  schemaVersion: typeof DOCUMENT_VERSION;
  editorModel: 'tiptap-prosemirror';
  content: JSONContent;
  annotations: CommentAnchor[];
};

export type LegacyDocument = {
  schemaVersion: 'peanut.richtext/0';
  html: string;
};

const allowedTags = [
  'a',
  'blockquote',
  'br',
  'em',
  'figure',
  'h1',
  'h2',
  'li',
  'ol',
  'p',
  'span',
  'strong',
  'ul',
];

export const cleanPastedHtml = (html: string): string =>
  DOMPurify.sanitize(html, {
    ALLOWED_TAGS: allowedTags,
    ALLOWED_ATTR: [
      'href',
      'rel',
      'target',
      'data-media-kind',
      'data-media-label',
      'data-media-ref',
    ],
  });

export const MediaPlaceholder = Node.create({
  name: 'mediaPlaceholder',
  group: 'block',
  atom: true,

  addAttributes() {
    return {
      ref: { default: '' },
      kind: { default: 'image' },
      label: { default: '' },
    };
  },

  parseHTML() {
    return [
      {
        tag: 'figure[data-media-ref]',
        getAttrs: (element) => {
          const node = element as HTMLElement;
          return {
            ref: node.dataset.mediaRef || '',
            kind: node.dataset.mediaKind || 'image',
            label: node.dataset.mediaLabel || '',
          };
        },
      },
    ];
  },

  renderHTML({ node }) {
    const { ref, kind, label } = node.attrs;
    return [
      'figure',
      {
        'data-media-ref': ref,
        'data-media-kind': kind,
        'data-media-label': label,
        class: 'media-placeholder',
        contenteditable: 'false',
      },
      ['span', { 'aria-hidden': 'true' }, kind === 'video' ? '🎬' : kind === 'audio' ? '🎙️' : '🖼️'],
      ['span', {}, label || ref],
    ];
  },
});

export const editorExtensions = (collaborationDocument?: Y.Doc) => [
  StarterKit.configure({
    undoRedo: collaborationDocument ? false : {},
    link: {
      autolink: false,
      openOnClick: false,
      HTMLAttributes: { rel: 'noopener noreferrer' },
    },
  }),
  MediaPlaceholder,
  ...(collaborationDocument
    ? [Collaboration.configure({ document: collaborationDocument })]
    : []),
];

export const initialContent: JSONContent = {
  type: 'doc',
  content: [
    {
      type: 'heading',
      attrs: { level: 1 },
      content: [{ type: 'text', text: '城市更新项目今天启动' }],
    },
    {
      type: 'paragraph',
      content: [{ type: 'text', text: '本报讯，项目将优先改善公共空间与社区服务。' }],
    },
    {
      type: 'bulletList',
      content: [
        {
          type: 'listItem',
          content: [{ type: 'paragraph', content: [{ type: 'text', text: '保留历史街巷肌理' }] }],
        },
      ],
    },
    {
      type: 'orderedList',
      attrs: { start: 1, type: null },
      content: [
        {
          type: 'listItem',
          content: [{ type: 'paragraph', content: [{ type: 'text', text: '公开征集居民建议' }] }],
        },
      ],
    },
    {
      type: 'paragraph',
      content: [
        {
          type: 'text',
          text: '查看项目说明',
          marks: [{ type: 'link', attrs: { href: 'https://example.com/news', target: '_blank', rel: 'noopener noreferrer', class: null } }],
        },
      ],
    },
    { type: 'mediaPlaceholder', attrs: { ref: 'asset:image:001', kind: 'image', label: '启动仪式现场图' } },
  ],
};

export const documentEnvelope = (
  editor: Editor,
  anchor: CommentAnchor | null
): CurrentDocument => ({
  schemaVersion: DOCUMENT_VERSION,
  editorModel: 'tiptap-prosemirror',
  content: editor.getJSON(),
  annotations: anchor ? [anchor] : [],
});

export const stableJson = (editor: Editor, anchor: CommentAnchor | null): string =>
  JSON.stringify(documentEnvelope(editor, anchor), null, 2);

export const mapAnchor = (
  anchor: CommentAnchor,
  transaction: Transaction
): CommentAnchor => {
  if (anchor.status === 'invalid' || !transaction.docChanged) return anchor;
  const from = transaction.mapping.mapResult(anchor.from, 1);
  const to = transaction.mapping.mapResult(anchor.to, -1);
  const quote = from.pos < to.pos ? transaction.doc.textBetween(from.pos, to.pos, ' ') : '';
  return {
    ...anchor,
    from: from.pos,
    to: to.pos,
    status:
      from.deleted || to.deleted || quote !== anchor.quote
        ? 'invalid'
        : 'active',
  };
};
