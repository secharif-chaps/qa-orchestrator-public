const mock = [
  {
    "id": 1,
    key: 'FR',
    value: 0.1,
  },
  {
    "id": 2,
    key: 'BR',
    value: 0.9,
  }
]

export const useBakus = () => {

  const getCountriesImplentation = () => {
    return mock
  }

  return {getCountriesImplentation}
}
