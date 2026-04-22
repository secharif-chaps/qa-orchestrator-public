export const useInitials = () => {
  const getInitials = (firstName?: string, lastName?: string): string => {
    const first = firstName?.charAt(0) ?? ''
    const last = lastName?.charAt(0) ?? ''
    return `${first}${last}`.toUpperCase()
  }

  return { getInitials }
}
