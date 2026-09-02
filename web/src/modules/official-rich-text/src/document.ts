import { Node, type Editor, type JSONContent } from '@tiptap/core';
import Collaboration from '@tiptap/extension-collaboration';
import StarterKit from '@tiptap/starter-kit';
import DOMPurify from 'dompurify';
import type * as Y from 'yjs';

export const DOCUMENT_VERSION = 'peanut.richtext/1' as const;

export interface RichTextDocumentValue {
  schemaVersion: typeof DOCUMENT_VERSION;
  editorModel: 'tiptap-prosemirror';
  content: JSONContent;
  annotations: [];
}

export const emptyDocument = (): RichTextDocumentValue => ({
  schemaVersion: DOCUMENT_VERSION,
  editorModel: 'tiptap-prosemirror',
  content: { type: 'doc', content: [{ type: 'paragraph' }] },
  annotations: [],
});

export const cleanPastedHtml = (html: string): string =>
  DOMPurify.sanitize(html, {
    ALLOWED_TAGS: [
      'a',
      'blockquote',
      'br',
      'code',
      'em',
      'figure',
      'h1',
      'h2',
      'h3',
      'li',
      'ol',
      'p',
      'pre',
      'span',
      'strong',
      'ul',
    ],
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
  addAttributes: () => ({
    ref: { default: '' },
    kind: { default: 'image' },
    label: { default: '' },
  }),
  parseHTML: () => [
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
  ],
  renderHTML: ({ node }) => [
    'figure',
    {
      'data-media-ref': node.attrs.ref,
      'data-media-kind': node.attrs.kind,
      'data-media-label': node.attrs.label,
      'class': 'rich-text-editor__media',
      'contenteditable': 'false',
    },
    ['span', {}, node.attrs.label || node.attrs.ref],
  ],
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

export const documentEnvelope = (editor: Editor): RichTextDocumentValue => ({
  schemaVersion: DOCUMENT_VERSION,
  editorModel: 'tiptap-prosemirror',
  content: editor.getJSON(),
  annotations: [],
});
