import type { PressItem } from '@/types/company'

export interface CategoryConfig {
  icon: string
}

export interface PressItemWithCategory extends PressItem {
  category: string
}

export const CATEGORY_CONFIG: Record<string, CategoryConfig> = {
  financial_news: { icon: 'fa-chart-line' },
  press_releases: { icon: 'fa-newspaper' },
  product_launches: { icon: 'fa-rocket' },
  executive_interviews: { icon: 'fa-microphone' },
  media_mentions: { icon: 'fa-quote-right' },
  articles: { icon: 'fa-file-lines' },
  partnership_announcements: { icon: 'fa-handshake' },
  awards_recognition: { icon: 'fa-trophy' },
}
