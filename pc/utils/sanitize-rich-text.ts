import DOMPurify from 'dompurify';

const sanitizeRichText = (html: string | null | undefined): string => {
  const value = html ?? ''
  // Nuxt SSR has no browser DOM. The API has already applied the authoritative
  // Symfony sanitizer; DOMPurify adds a second boundary after hydration.
  return typeof window === 'undefined' ? value : DOMPurify.sanitize(value)
}

export default sanitizeRichText
