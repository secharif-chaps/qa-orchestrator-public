export default defineI18nConfig(() => ({
  datetimeFormats: {
    en: {
      short: {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      },
      long: {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      },
      time: {
        hour: '2-digit',
        minute: '2-digit',
      },
      eventDate: {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      },
      eventDateTime: {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
      },
    },
    fr: {
      short: {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      },
      long: {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      },
      time: {
        hour: '2-digit',
        minute: '2-digit',
      },
      eventDate: {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      },
      eventDateTime: {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
      },
    },
  },
}));
