<template>
  <OButton
    type="tertiary"
    @click="downloadPPT"
    icon="fa-download"
  >
  </OButton>
</template>

<script lang="ts" setup>
import { OButton } from '@owlint/feathers-vue'
import pptxgen from 'pptxgenjs'

const { company } = useCompanyData()

// Define color constants to match Tailwind colors
const COLORS = {
  primary: '10B981', // emerald-500
  background: 'F1F5F9', // slate-100
  cardBackground: 'FFFFFF', // white
  titleText: '000000', // black
  secondaryText: '475569', // slate-600
}

// Function to create rounded rectangle for card background
const addCardBackground = (slide, options = {}) => {
  const defaultOptions = {
    x: 0.5,
    y: 1.2,
    w: 9.0,
    h: 4.5,
    fill: { color: COLORS.cardBackground },
    lineSize: 0,
    rounded: true,
    shadow: {
      type: 'outer',
      angle: 45,
      blur: 3,
      offset: 2,
      color: 'COCOCO',
      opacity: 0.2,
    },
  }

  slide.addShape('ROUNDED_RECTANGLE', { ...defaultOptions, ...options })
}

// Function to add slide title
const addSlideTitle = (slide, title, options = {}) => {
  const defaultOptions = {
    x: 0.5,
    y: 0.5,
    fontSize: 24,
    bold: true,
    color: COLORS.titleText,
    fontFace: 'Arial',
  }

  slide.addText(title, { ...defaultOptions, ...options })
}

// Function to create text block with label and value
const addInfoBlock = (slide, label, value, x, y) => {
  slide.addText(label, {
    x,
    y,
    fontSize: 14,
    bold: true,
    color: COLORS.titleText,
    fontFace: 'Arial',
  })

  slide.addText(value || 'N/A', {
    x,
    y: y + 0.4,
    fontSize: 12,
    color: COLORS.secondaryText,
    fontFace: 'Arial',
    breakLine: true,
    w: 4.0,
  })
}

// Function to create list items
const addListItems = (slide, title, items, x, y) => {
  slide.addText(title, {
    x,
    y,
    fontSize: 14,
    bold: true,
    color: COLORS.titleText,
    fontFace: 'Arial',
  })

  if (!items || items.length === 0) {
    slide.addText('No items available', {
      x,
      y: y + 0.4,
      fontSize: 12,
      color: COLORS.secondaryText,
      fontFace: 'Arial',
    })
    return
  }

  items.forEach((item, index) => {
    slide.addText(`• ${item}`, {
      x,
      y: y + 0.4 + index * 0.3,
      fontSize: 12,
      color: COLORS.secondaryText,
      fontFace: 'Arial',
      breakLine: true,
      w: 4.0,
    })
  })
}

// Create slide for company profile
const createProfileSlide = (pptx, company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Company Profile')
  addCardBackground(slide)

  // Company name with larger font
  slide.addText(company.profile?.name || 'Company Name', {
    x: 1.0,
    y: 1.5,
    fontSize: 20,
    bold: true,
    color: COLORS.primary,
    fontFace: 'Arial',
  })

  // Add company catchphrase
  if (company.profile?.catchphrase) {
    slide.addText(company.profile.catchphrase, {
      x: 1.0,
      y: 2.0,
      fontSize: 14,
      italic: true,
      color: COLORS.secondaryText,
      fontFace: 'Arial',
      breakLine: true,
      w: 8.0,
    })
  }

  // Company details in 2 columns
  addInfoBlock(slide, 'Business Line', company.profile?.business_line, 1.0, 2.7)
  addInfoBlock(slide, 'Website', company.profile?.website, 5.0, 2.7)
  addInfoBlock(
    slide,
    'Established',
    company.profile?.establishment_year,
    1.0,
    3.7
  )
  addInfoBlock(slide, 'Employees', company.profile?.employee_count, 5.0, 3.7)
  addInfoBlock(slide, 'Revenue', company.profile?.revenue, 1.0, 4.7)

  // Add group name if available
  if (company.profile?.group_name) {
    addInfoBlock(slide, 'Group', company.profile.group_name, 5.0, 4.7)
  }
}

// Create slide for products and services
const createProductsSlide = (pptx, company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Products and Services')
  addCardBackground(slide)

  const productsServices = company.products_and_services || {}

  // Add product range in first column
  addListItems(slide, 'Product Range', productsServices.product_range, 1.0, 1.5)

  // Add partner brands in second column
  addListItems(
    slide,
    'Partner Brands',
    productsServices.partner_brands,
    5.0,
    1.5
  )

  // Add private labels at bottom
  addListItems(
    slide,
    'Private Labels',
    productsServices.private_labels,
    1.0,
    4.0
  )
}

// Create slide for target audience and customer base
const createTargetAudienceSlide = (pptx, company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Target Audience & Customer Base')
  addCardBackground(slide)

  const targetAudience = company.target_audience_and_customer_base || {}

  // Add customer type
  addInfoBlock(slide, 'Customer Type', targetAudience.customer_type, 1.0, 1.5)

  // Add marketing positioning with more space
  slide.addText('Marketing Positioning', {
    x: 1.0,
    y: 2.5,
    fontSize: 14,
    bold: true,
    color: COLORS.titleText,
    fontFace: 'Arial',
  })

  slide.addText(targetAudience.marketing_positioning || 'N/A', {
    x: 1.0,
    y: 2.9,
    fontSize: 12,
    color: COLORS.secondaryText,
    fontFace: 'Arial',
    breakLine: true,
    w: 8.0,
    h: 2.0,
  })
}

// Create slide for digital strategy
const createDigitalStrategySlide = (pptx, company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Digital Strategy & Social Media')
  addCardBackground(slide)

  const digitalStrategy = company.digital_strategy_and_social_media || {}

  // Add digital strategy
  slide.addText('Digital Strategy', {
    x: 1.0,
    y: 1.5,
    fontSize: 14,
    bold: true,
    color: COLORS.titleText,
    fontFace: 'Arial',
  })

  slide.addText(digitalStrategy.digital_strategy || 'N/A', {
    x: 1.0,
    y: 1.9,
    fontSize: 12,
    color: COLORS.secondaryText,
    fontFace: 'Arial',
    breakLine: true,
    w: 8.0,
  })

  // Add loyalty program
  slide.addText('Loyalty Program', {
    x: 1.0,
    y: 3.0,
    fontSize: 14,
    bold: true,
    color: COLORS.titleText,
    fontFace: 'Arial',
  })

  slide.addText(digitalStrategy.loyalty_program || 'N/A', {
    x: 1.0,
    y: 3.4,
    fontSize: 12,
    color: COLORS.secondaryText,
    fontFace: 'Arial',
    breakLine: true,
    w: 8.0,
  })

  // Add online services
  addListItems(
    slide,
    'Online Services',
    digitalStrategy.online_services,
    1.0,
    4.2
  )

  // Add social media
  if (company.social_media && company.social_media.length > 0) {
    slide.addText('Social Media Presence', {
      x: 5.0,
      y: 4.2,
      fontSize: 14,
      bold: true,
      color: COLORS.titleText,
      fontFace: 'Arial',
    })

    company.social_media.forEach((social, index) => {
      slide.addText(`• ${social.name}`, {
        x: 5.0,
        y: 4.6 + index * 0.3,
        fontSize: 12,
        color: COLORS.secondaryText,
        fontFace: 'Arial',
      })
    })
  }
}

// Create slide for CSR initiatives
const createCSRSlide = (pptx, company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Corporate Social Responsibility')
  addCardBackground(slide)

  const csr = company.csr || {}

  // Add responsibility initiatives in first column
  addListItems(
    slide,
    'Responsibility Initiatives',
    csr.responsibility_initiatives,
    1.0,
    1.5
  )

  // Add charity actions in second column
  addListItems(slide, 'Charity Actions', csr.charity_actions, 5.0, 1.5)
}

// Create slide for recent news
const createNewsSlide = (pptx, company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Recent News')
  addCardBackground(slide)

  // Add recent news items
  addListItems(slide, 'Latest Updates', company.recent_news, 1.0, 1.5)
}

// Create insights slide
const createInsightsSlide = (pptx, company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Company Insights')
  addCardBackground(slide)

  // Add insights text
  slide.addText('Key Insights', {
    x: 1.0,
    y: 1.5,
    fontSize: 14,
    bold: true,
    color: COLORS.titleText,
    fontFace: 'Arial',
  })

  slide.addText(company.insights || 'No insights available', {
    x: 1.0,
    y: 1.9,
    fontSize: 12,
    color: COLORS.secondaryText,
    fontFace: 'Arial',
    breakLine: true,
    w: 8.0,
    h: 3.5,
  })
}

// Create title slide
const createTitleSlide = (pptx, company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  // Create card background with different dimensions
  slide.addShape('ROUNDED_RECTANGLE', {
    x: 0.5,
    y: 1.5,
    w: 9.0,
    h: 3.0,
    fill: { color: COLORS.cardBackground },
    lineSize: 0,
    rounded: true,
    shadow: {
      type: 'outer',
      angle: 45,
      blur: 3,
      offset: 2,
      color: 'COCOCO',
      opacity: 0.2,
    },
  })

  // Add company name in large font as main title
  slide.addText(company.profile?.name || 'Company Overview', {
    x: 0.5,
    y: 1.8,
    w: 9.0,
    fontSize: 36,
    bold: true,
    color: COLORS.primary,
    fontFace: 'Arial',
    align: 'center',
  })

  // Add subtitle with catchphrase if available
  if (company.profile?.catchphrase) {
    slide.addText(company.profile.catchphrase, {
      x: 0.5,
      y: 2.8,
      w: 9.0,
      fontSize: 16,
      italic: true,
      color: COLORS.secondaryText,
      fontFace: 'Arial',
      align: 'center',
      breakLine: true,
    })
  }

  // Add date at bottom
  const currentDate = new Date().toLocaleDateString()
  slide.addText(`Generated on ${currentDate}`, {
    x: 0.5,
    y: 4.8,
    w: 9.0,
    fontSize: 10,
    color: COLORS.secondaryText,
    fontFace: 'Arial',
    align: 'center',
  })
}

const downloadPPT = async () => {
  if (!company.value) return

  try {
    // Create new PowerPoint presentation
    const pptx = new pptxgen()
    pptx.layout = 'LAYOUT_WIDE'

    // Create all slides
    createTitleSlide(pptx, company.value)

    // Prioritize insights slide as the first content slide if available
    if (company.value.insights) {
      createInsightsSlide(pptx, company.value)
    }

    createProfileSlide(pptx, company.value)

    // Only create slides for sections that have data
    if (company.value.products_and_services) {
      createProductsSlide(pptx, company.value)
    }

    if (company.value.target_audience_and_customer_base) {
      createTargetAudienceSlide(pptx, company.value)
    }

    if (company.value.digital_strategy_and_social_media) {
      createDigitalStrategySlide(pptx, company.value)
    }

    if (company.value.csr) {
      createCSRSlide(pptx, company.value)
    }

    if (company.value.recent_news && company.value.recent_news.length > 0) {
      createNewsSlide(pptx, company.value)
    }

    // Generate filename from company name or use default
    const fileName = company.value.profile?.name
      ? `${company.value.profile.name.replace(
          /[^a-z0-9]/gi,
          '_'
        )}_Overview.pptx`
      : 'Company_Overview.pptx'

    // Write file
    pptx.writeFile({ fileName })
  } catch (error) {
    console.error('Error generating PowerPoint:', error)
    // Here you could add user notification about the error
  }
}
</script>
