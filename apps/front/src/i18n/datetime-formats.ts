import type { DateTimeFormat } from '@intlify/core-base'

export const datetimeFormats: Record<string, DateTimeFormat> = {
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
    fullDate: {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
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
    fullDate: {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    },
  },
}
