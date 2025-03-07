<template>
  <NuxtLayout name="main"> <NuxtPage /> </NuxtLayout>
</template>

<script lang="ts" setup>
const companyStore = useCompanyStore()

const mock = false

onMounted(() => {
  // Initialize the company store with the current company
  if (mock) {
    companyStore.setCompanyData('Sephora', {
      meta: { company_name: 'Sephora', query_date: '2023-10-04' },
      insights: {
        value:
          'Sephora is a leading global beauty retailer, offering a wide range of cosmetics, skincare, fragrance, and beauty tools. Founded in 1970, it has grown to become a major player in the beauty industry with a strong digital presence and extensive product range.',
        source: 'https://www.sephora.fr',
      },
      profile: {
        name: { value: 'Sephora', source: 'https://www.sephora.fr' },
        website: { value: 'https://www.sephora.fr', source: 'N/A' },
        group_name: {
          value: 'LVMH',
          source: 'https://www.sephora.fr/A-Propos-de-Sephora/Notre-Histoire',
        },
        business_line: {
          value: 'Beauty and Cosmetics',
          source: 'https://www.sephora.fr',
        },
        catchphrase: {
          value: "Let's Beauty Together",
          source: 'https://www.sephora.fr',
        },
        establishment_year: {
          value: '1970',
          source: 'https://www.sephora.fr/A-Propos-de-Sephora/Notre-Histoire',
        },
        employee_count: {
          value: '30,000',
          source: 'https://www.linkedin.com/company/sephora/about/',
        },
        revenue: {
          value: '$10B',
          source: 'https://www.forbes.com/companies/sephora/',
        },
      },
      products_and_services: {
        product_range: [
          { value: 'Makeup', source: 'https://www.sephora.fr/m/makeup.html' },
          {
            value: 'Skincare',
            source: 'https://www.sephora.fr/m/skincare.html',
          },
          {
            value: 'Fragrance',
            source: 'https://www.sephora.fr/m/fragrance.html',
          },
          {
            value: 'Hair Care',
            source: 'https://www.sephora.fr/m/hair-care.html',
          },
          {
            value: 'Beauty Tools',
            source: 'https://www.sephora.fr/m/beauty-tools.html',
          },
        ],
        partner_brands: [
          {
            value: 'Fenty Beauty',
            source: 'https://www.sephora.fr/m/brands/fenty-beauty.html',
          },
          {
            value: 'Drunk Elephant',
            source: 'https://www.sephora.fr/m/brands/drunk-elephant.html',
          },
          {
            value: 'Pat McGrath Labs',
            source: 'https://www.sephora.fr/m/brands/pat-mcgrath-labs.html',
          },
        ],
        private_labels: [
          {
            value: 'Sephora Collection',
            source: 'https://www.sephora.fr/m/brands/sephora-collection.html',
          },
        ],
      },
      target_audience_and_customer_base: {
        customer_type: {
          value: 'General Public',
          source: 'https://www.sephora.fr',
        },
        marketing_positioning: {
          value: 'Accessible high-end with an immersive customer experience',
          source: 'https://www.marketingweek.com/sephora-beauty-retail/',
        },
      },
      digital_strategy_and_social_media: {
        digital_strategy: {
          value:
            'Sephora focuses on creating a seamless omnichannel experience, integrating online and in-store shopping with innovative digital tools such as the Virtual Artist and Beauty Insider program.',
          source: 'https://www.sephora.fr/digital',
        },
        loyalty_program: {
          value:
            'Beauty Insider program offers rewards, exclusive events, and personalized experiences.',
          source: 'https://www.sephora.fr/beauty/loyalty-program',
        },
        online_services: [
          {
            value: 'Virtual Artist',
            source: 'https://www.sephora.fr/virtual-artist',
          },
          {
            value: 'Beauty Insider Community',
            source: 'https://www.sephora.fr/beauty/community',
          },
        ],
      },
      csr: {
        responsibility_initiatives: [
          {
            value: 'Sephora Stands',
            source: 'https://www.sephora.fr/sephora-stands',
          },
          {
            value: 'Sustainability Commitments',
            source: 'https://www.sephora.fr/sustainability',
          },
        ],
        charity_actions: [
          {
            value: 'Sephora Accelerate',
            source: 'https://www.sephora.fr/sephora-accelerate',
          },
          {
            value: 'Classes for Confidence',
            source: 'https://www.sephora.fr/classes-for-confidence',
          },
        ],
      },
      recent_news: [
        {
          value: "New collaboration with Rihanna's Fenty Beauty",
          source:
            'https://www.forbes.com/sites/clareoconnor/2017/09/08/fenty-beauty-rihanna-sephora-launch/?sh=d44d86b83508',
        },
        {
          value: 'International expansion to China',
          source:
            'https://www.reuters.com/article/us-sephora-china-expansion-idUSKBN1ZM05D',
        },
        {
          value: 'New initiative for sustainable packaging',
          source: 'https://www.sephora.fr/sustainability-initiatives',
        },
      ],
      social_media: [
        {
          name: 'facebook',
          url: {
            value: 'https://www.facebook.com/SephoraFrance/',
            source: 'website footer',
          },
        },
        {
          name: 'linkedin',
          url: {
            value: 'https://www.linkedin.com/company/sephora/',
            source: 'website footer',
          },
        },
      ],
    })
  }
})
</script>
