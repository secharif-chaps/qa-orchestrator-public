import type { DateTimeFormats } from 'vue-i18n'

export const datetimeFormats: DateTimeFormats = {
  'en-US': {
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
  'fr-FR': {
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
}
