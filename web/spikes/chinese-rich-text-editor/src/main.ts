import { Editor, Node, type JSONContent } from '@tiptap/core';
import type { Transaction } from '@tiptap/pm/state';
import StarterKit from '@tiptap/starter-kit';
import DOMPurify from 'dompurify';
import './style.css';

const DOCUMENT_VERSION = 'peanut.richtext/1' as const;

type CommentAnchor = {
  id: string;
  from: number;
  to: number;
  quote: string;
  status: 'active' | 'invalid';
};

type CurrentDocument = {
  schemaVersion: typeof DOCUMENT_VERSION;
  editorModel: 'tiptap-prosemirror';
  content: JSONContent;
  annotations: CommentAnchor[];
};

type LegacyDocument = {
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

const cleanPastedHtml = (html: string): string =>
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

const MediaPlaceholder = Node.create({
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

const editorExtensions = () => [
  StarterKit.configure({
    link: {
      autolink: false,
      openOnClick: false,
      HTMLAttributes: { rel: 'noopener noreferrer' },
    },
  }),
  MediaPlaceholder,
];

const initialContent: JSONContent = {
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
        { type: 'text', text: '查看项目说明', marks: [{ type: 'link', attrs: { href: 'https://example.com/news', target: '_blank', rel: 'noopener noreferrer', class: null } }] },
      ],
    },
    { type: 'mediaPlaceholder', attrs: { ref: 'asset:image:001', kind: 'image', label: '启动仪式现场图' } },
  ],
};

const element = <T extends HTMLElement>(selector: string): T => {
  const match = document.querySelector<T>(selector);
  if (!match) throw new Error(`missing element: ${selector}`);
  return match;
};

const editorElement = element<HTMLDivElement>('#editor');
const jsonElement = element<HTMLElement>('#document-json');
const anchorStatus = element<HTMLOutputElement>('#anchor-status');
const checkStatus = element<HTMLElement>('#check-status');
const legacyElement = element<HTMLDivElement>('#legacy-render');
let commentAnchor: CommentAnchor | null = null;
let readonlyEditor: Editor | null = null;

const documentEnvelope = (editor: Editor, anchor: CommentAnchor | null): CurrentDocument => ({
  schemaVersion: DOCUMENT_VERSION,
  editorModel: 'tiptap-prosemirror',
  content: editor.getJSON(),
  annotations: anchor ? [anchor] : [],
});

const stableJson = (editor: Editor, anchor: CommentAnchor | null): string =>
  JSON.stringify(documentEnvelope(editor, anchor), null, 2);

const renderAnchor = () => {
  anchorStatus.value = commentAnchor
    ? commentAnchor.status === 'active'
      ? `评论锚点有效：“${commentAnchor.quote}”`
      : `位置已失效：“${commentAnchor.quote}”`
    : '尚未创建锚点';
};

const updateJson = () => {
  jsonElement.textContent = stableJson(editor, commentAnchor);
};

const mapAnchor = (anchor: CommentAnchor, transaction: Transaction): CommentAnchor => {
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

const editor = new Editor({
  element: editorElement,
  extensions: editorExtensions(),
  content: initialContent,
  editorProps: {
    attributes: {
      'aria-label': '中文新闻稿正文',
      'aria-multiline': 'true',
      role: 'textbox',
      spellcheck: 'true',
    },
    transformPastedHTML: cleanPastedHtml,
  },
  onTransaction: ({ transaction }) => {
    if (commentAnchor) commentAnchor = mapAnchor(commentAnchor, transaction);
    renderAnchor();
  },
  onUpdate: updateJson,
});

const setStatus = (message: string, state: 'ok' | 'error' | 'idle' = 'idle') => {
  checkStatus.textContent = message;
  checkStatus.dataset.state = state;
};

document.querySelectorAll<HTMLButtonElement>('[data-command]').forEach((button) => {
  button.addEventListener('click', () => {
    const chain = editor.chain().focus();
    const commands: Record<string, () => void> = {
      heading: () => chain.toggleHeading({ level: 1 }).run(),
      paragraph: () => chain.setParagraph().run(),
      bullet: () => chain.toggleBulletList().run(),
      ordered: () => chain.toggleOrderedList().run(),
      undo: () => chain.undo().run(),
      redo: () => chain.redo().run(),
    };
    commands[button.dataset.command || '']?.();
  });
});

element<HTMLButtonElement>('#set-link').addEventListener('click', () => {
  const value = element<HTMLInputElement>('#link-url').value.trim();
  try {
    const url = new URL(value);
    if (!['http:', 'https:', 'mailto:'].includes(url.protocol)) throw new Error('protocol');
    editor.chain().focus().extendMarkRange('link').setLink({ href: value }).run();
    setStatus('链接已应用', 'ok');
  } catch {
    setStatus('链接无效：仅允许 http、https 或 mailto', 'error');
  }
});

element<HTMLButtonElement>('#insert-media').addEventListener('click', () => {
  const ref = element<HTMLInputElement>('#media-ref').value.trim();
  const kind = element<HTMLSelectElement>('#media-kind').value;
  if (!/^[a-z][a-z0-9._:-]{2,80}$/.test(ref)) {
    setStatus('媒体引用格式无效', 'error');
    return;
  }
  editor.chain().focus().insertContent({
    type: 'mediaPlaceholder',
    attrs: { ref, kind, label: `待加载${kind === 'video' ? '视频' : kind === 'audio' ? '音频' : '图片'}` },
  }).run();
  setStatus('媒体占位已插入', 'ok');
});

element<HTMLButtonElement>('#add-comment').addEventListener('click', () => {
  const { from, to } = editor.state.selection;
  const quote = editor.state.doc.textBetween(from, to, ' ');
  if (from === to || !quote) {
    setStatus('请先选择正文文字', 'error');
    return;
  }
  commentAnchor = { id: 'comment-spike-1', from, to, quote, status: 'active' };
  renderAnchor();
  updateJson();
});

const renderReadOnly = (document: CurrentDocument | LegacyDocument | { schemaVersion: string }) => {
  readonlyEditor?.destroy();
  readonlyEditor = null;
  legacyElement.replaceChildren();
  if (document.schemaVersion === 'peanut.richtext/0' && 'html' in document) {
    legacyElement.innerHTML = cleanPastedHtml(document.html);
    return;
  }
  if (document.schemaVersion === DOCUMENT_VERSION && 'content' in document) {
    readonlyEditor = new Editor({
      element: legacyElement,
      extensions: editorExtensions(),
      content: document.content,
      editable: false,
    });
    return;
  }
  legacyElement.textContent = `不支持的文档版本：${document.schemaVersion}`;
};

renderReadOnly({
  schemaVersion: 'peanut.richtext/0',
  html: '<h2>历史稿件</h2><p>旧版 HTML 只读显示，<strong>不会写回</strong>新文档。</p>',
});

const assert = (condition: unknown, message: string) => {
  if (!condition) throw new Error(message);
};

const runFocusedCheck = () => {
  const checkHost = document.createElement('div');
  const checkEditor = new Editor({
    element: checkHost,
    extensions: editorExtensions(),
    content: initialContent,
    editorProps: { transformPastedHTML: cleanPastedHtml },
  });
  let checkAnchor: CommentAnchor | null = null;
  checkEditor.on('transaction', ({ transaction }) => {
    if (checkAnchor) checkAnchor = mapAnchor(checkAnchor, transaction);
  });

  try {
    const clean = cleanPastedHtml(
      '<p class="MsoNormal" style="color:red">安全<script>坏</script><a href="javascript:alert(1)">链接</a></p>'
    );
    assert(!/script|style=|class=|javascript:/i.test(clean), '粘贴清洗失败');

    const serialized = stableJson(checkEditor, null);
    assert(serialized === stableJson(checkEditor, null), 'JSON 序列化不稳定');
    ['heading', 'paragraph', 'bulletList', 'orderedList', 'link', 'mediaPlaceholder'].forEach((type) =>
      assert(serialized.includes(`\"${type}\"`), `JSON 缺少 ${type}`)
    );

    checkEditor.commands.setContent('<p>甲乙丙</p>');
    checkEditor.commands.setTextSelection({ from: 1, to: 3 });
    checkAnchor = { id: 'check', from: 1, to: 3, quote: '甲乙', status: 'active' };
    checkEditor.commands.setTextSelection(1);
    checkEditor.commands.insertContent('前');
    assert(checkAnchor.status === 'active', '锚点未随前置文本移动');
    checkEditor.commands.setTextSelection({ from: 2, to: 4 });
    checkEditor.commands.insertContent('改');
    assert(checkAnchor.status === 'invalid', '锚点内容变化后未明确失效');

    checkEditor.commands.setContent('<p>起</p>');
    checkEditor.commands.setTextSelection(2);
    checkEditor.commands.insertContent('点');
    checkEditor.commands.undo();
    assert(checkEditor.getText() === '起', '撤销失败');
    checkEditor.commands.redo();
    assert(checkEditor.getText() === '起点', '重做失败');

    const legacyHost = document.createElement('div');
    legacyHost.innerHTML = cleanPastedHtml('<h2>旧稿</h2><script>坏</script>');
    assert(legacyHost.textContent === '旧稿' && !legacyHost.querySelector('script'), '旧版只读渲染失败');
    setStatus('通过：粘贴清洗、结构 JSON、锚点失效、撤销/重做、旧版只读', 'ok');
    return { ok: true, checks: 5 };
  } catch (error) {
    setStatus(`失败：${error instanceof Error ? error.message : String(error)}`, 'error');
    throw error;
  } finally {
    checkEditor.destroy();
  }
};

element<HTMLButtonElement>('#run-check').addEventListener('click', runFocusedCheck);
renderAnchor();
updateJson();

Object.assign(window, {
  __richTextEditorSpike: {
    editor,
    cleanPastedHtml,
    documentEnvelope: () => documentEnvelope(editor, commentAnchor),
    runFocusedCheck,
  },
});
