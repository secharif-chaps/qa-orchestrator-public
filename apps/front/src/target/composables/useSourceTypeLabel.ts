import { useI18n } from 'vue-i18n'

export function useSourceTypeLabel() {
  const { t } = useI18n()

  const getSourceTypeLabel = (type: string | undefined): string => {
    if (!type) return t('target.sourceTypes.unknown')

    // Social-media sub-types (e.g. social_media:facebook:page) all map to the
    // parent "Social Media" label.
    if (type.startsWith('social_media:')) return t('target.sourceTypes.social_media')

    const labels: Record<string, string> = {
      blog: t('target.sourceTypes.blog'),
      'document:espacenet:advanced_search': t(
        'target.sourceTypes.document:espacenet:advanced_search',
      ),
      'document:espacenet:smart_search': t('target.sourceTypes.document:espacenet:smart_search'),
      'document:ieeexplore:publications': t('target.sourceTypes.document:ieeexplore:publications'),
      'document:questel:search': t('target.sourceTypes.document:questel:search'),
      'domain:company_name': t('target.sourceTypes.domain:company_name'),
      'domain:search': t('target.sourceTypes.domain:search'),
      error: t('target.sourceTypes.error'),
      'event:geoconfirmed:zone': t('target.sourceTypes.event:geoconfirmed:zone'),
      expert_opinion: t('target.sourceTypes.expert_opinion'),
      'file:ftp': t('target.sourceTypes.file:ftp'),
      forum: t('target.sourceTypes.forum'),
      'image:google:images': t('target.sourceTypes.image:google:images'),
      'news:newsapi': t('target.sourceTypes.news:newsapi'),
      'news:newsapi:headlines': t('target.sourceTypes.news:newsapi:headlines'),
      newsletter: t('target.sourceTypes.newsletter'),
      official_publication: t('target.sourceTypes.official_publication'),
      rss_feed: t('target.sourceTypes.rss_feed'),
      scientific_publication: t('target.sourceTypes.scientific_publication'),
      social_media: t('target.sourceTypes.social_media'),
      'social_media:facebook:group': t('target.sourceTypes.social_media:facebook:group'),
      'social_media:facebook:page': t('target.sourceTypes.social_media:facebook:page'),
      'social_media:facebook:search': t('target.sourceTypes.social_media:facebook:search'),
      'social_media:facebook:user': t('target.sourceTypes.social_media:facebook:user'),
      'social_media:gab:group': t('target.sourceTypes.social_media:gab:group'),
      'social_media:gab:search': t('target.sourceTypes.social_media:gab:search'),
      'social_media:instagram:hashtag': t('target.sourceTypes.social_media:instagram:hashtag'),
      'social_media:instagram:search': t('target.sourceTypes.social_media:instagram:search'),
      'social_media:instagram:user': t('target.sourceTypes.social_media:instagram:user'),
      'social_media:linkedin:company': t('target.sourceTypes.social_media:linkedin:company'),
      'social_media:linkedin:group': t('target.sourceTypes.social_media:linkedin:group'),
      'social_media:linkedin:search': t('target.sourceTypes.social_media:linkedin:search'),
      'social_media:linkedin:user': t('target.sourceTypes.social_media:linkedin:user'),
      'social_media:mastodon:search': t('target.sourceTypes.social_media:mastodon:search'),
      'social_media:mastodon:user': t('target.sourceTypes.social_media:mastodon:user'),
      'social_media:reddit:subreddit': t('target.sourceTypes.social_media:reddit:subreddit'),
      'social_media:reddit:user': t('target.sourceTypes.social_media:reddit:user'),
      'social_media:telegram:channel': t('target.sourceTypes.social_media:telegram:channel'),
      'social_media:x:hashtag': t('target.sourceTypes.social_media:x:hashtag'),
      'social_media:x:search': t('target.sourceTypes.social_media:x:search'),
      'social_media:x:user': t('target.sourceTypes.social_media:x:user'),
      unknown: t('target.sourceTypes.unknown'),
      'video:dailymotion:search': t('target.sourceTypes.video:dailymotion:search'),
      'video:dailymotion:user': t('target.sourceTypes.video:dailymotion:user'),
      'video:odysee': t('target.sourceTypes.video:odysee'),
      'video:youtube:channel': t('target.sourceTypes.video:youtube:channel'),
      'video:youtube:comments': t('target.sourceTypes.video:youtube:comments'),
      'video:youtube:playlist': t('target.sourceTypes.video:youtube:playlist'),
      'video:youtube:search': t('target.sourceTypes.video:youtube:search'),
      website: t('target.sourceTypes.website'),
    }

    return labels[type] ?? type
  }

  return { getSourceTypeLabel }
}
