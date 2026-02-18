export type DocumentType = 'pdf' | 'html'

export function useDocumentIcon() {
  const getDocumentIcon = (documentType: string): string => {
    switch (documentType.toLowerCase()) {
      case 'pdf':
        return 'fa-file-pdf'
      case 'html':
        return 'fa-globe'
      default:
        return 'fa-file-lines'
    }
  }

  const getDocumentType = (documentType: string): DocumentType | null => {
    const normalizedType = documentType.toLowerCase()

    if (normalizedType === 'pdf' || normalizedType === 'html') {
      return normalizedType as DocumentType
    }

    return null
  }

  return {
    getDocumentIcon,
    getDocumentType,
  }
}
