import DOMPurify from 'dompurify';

export const sanitizeRichText = (html: string | null | undefined): string =>
  DOMPurify.sanitize(html ?? '');
