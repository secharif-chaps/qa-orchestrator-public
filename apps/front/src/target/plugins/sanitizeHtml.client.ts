import type { App } from 'vue'
import DOMPurify, { type Config } from 'dompurify'

const sanitizeConfig: Config = {
  ALLOWED_ATTR: ['href', 'title', 'rel', 'target', 'class', 'id', 'src', 'alt', 'width', 'height'],
  ALLOWED_TAGS: [
    'p',
    'br',
    'strong',
    'em',
    'u',
    's',
    'a',
    'ul',
    'ol',
    'li',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'blockquote',
    'code',
    'pre',
    'img',
    'table',
    'thead',
    'tbody',
    'tr',
    'td',
    'th',
  ],
}

export default {
  install(app: App) {
    app.directive('sanitize-html', {
      beforeMount(el, binding) {
        el.innerHTML = DOMPurify.sanitize(binding.value, sanitizeConfig)
      },
      updated(el, binding) {
        el.innerHTML = DOMPurify.sanitize(binding.value, sanitizeConfig)
      },
    })
  },
}
