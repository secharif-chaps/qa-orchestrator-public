import { marked } from 'marked';
import { computed, unref, type ComputedRef, type MaybeRef } from 'vue';

export interface MarkdownOptions {
  gfm?: boolean;
  breaks?: boolean;
  customTypographyRules?: boolean;
}

function applyCustomTypographyRules(html: string): string {
  /**
   * Insert non-breaking space before strong punctuation (?!:;).
   * Only applies when there's a space before the punctuation (like in French).
   * Preserves URLs and other technical patterns.
   * Transparent on other languages not using spaces before these symbols.
   */
  return html.replace(/ ([?!:;])/g, '\u00A0$1');
}

marked.use({
  renderer: {
    link({ href, title, text }) {
      const titleAttr = title ? ` title="${title}"` : '';
      return `<a href="${href}"${titleAttr} target="_blank" rel="noopener noreferrer">${text}</a>`;
    },
    codespan({ text }) {
      // Convert URLs to clickable links
      const urlRegex = /^(https?:\/\/[^\s]+)$/;
      if (urlRegex.test(text)) {
        return `<a href="${text}" target="_blank" rel="noopener noreferrer" class="markdown-url-link">${text}</a>`;
      }
      // Otherwise, render as normal inline code
      return `<code>${text}</code>`;
    },
  },
});

export function useMarkdown(options: MarkdownOptions = {}) {
  const defaultOptions: MarkdownOptions = {
    gfm: true,
    breaks: false,
    customTypographyRules: true,
  };

  const mergedOptions = { ...defaultOptions, ...options };

  const toHtml = (
    content: MaybeRef<string | undefined | null>,
  ): ComputedRef<string> => {
    return computed(() => {
      let contentValue = unref(content);
      if (!contentValue) return '';

      // Remove trailing spaces before newlines to prevent unwanted line breaks
      // that would interfere with nested list syntax
      contentValue = contentValue.replace(/ +\n/g, '\n');

      let result = marked.parse(contentValue, mergedOptions);

      // Apply typography rules AFTER HTML conversion to avoid breaking markdown syntax
      if (mergedOptions.customTypographyRules && typeof result === 'string') {
        result = applyCustomTypographyRules(result);
      }

      return typeof result === 'string' ? result : '';
    });
  };

  return {
    toHtml,
  };
}
