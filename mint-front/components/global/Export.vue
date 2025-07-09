<template>
  <div>
    <OButton
      type="tertiary"
      @click="showModal = true"
      icon="fa-download"
    >
    </OButton>
    
    <ExportModal
      :is-open="showModal"
      :company="company"
      @close="showModal = false"
      @export="handleExport"
    />
  </div>
</template>

<script lang="ts" setup>
import type { Company, SourcedValue } from '@/types'
import { OButton } from '@owlint/feathers-vue'
import pptxgen from 'pptxgenjs'

const { company } = useCompanyData()
const showModal = ref(false)

// Define color constants to match Tailwind colors
const COLORS = {
  primary: '10B981', // emerald-500
  background: 'F1F5F9', // slate-100
  cardBackground: 'FFFFFF', // white
  titleText: '000000', // black
  secondaryText: '475569', // slate-600
  sourceText: '94A3B8', // slate-400
}

// Helper function to safely get value from SourcedValue type
const getValue = <T>(
  sourcedValue: SourcedValue<T> | undefined
): T | undefined => {
  return sourcedValue?.value
}

// Helper function to get sources from an array of SourcedValue items
const getSourcesFromArray = <T>(
  items: SourcedValue<T>[] | undefined
): string[] => {
  if (!items || items.length === 0) return []
  return items
    .map((item) => item.source)
    .filter((source) => source && source.trim() !== '')
}

// Helper function to get sources from object properties
const getSourcesFromObject = (
  obj: Record<string, any> | undefined
): string[] => {
  if (!obj) return []

  const sources: string[] = []

  Object.values(obj).forEach((value) => {
    if (value && typeof value === 'object') {
      if (
        'source' in value &&
        typeof value.source === 'string' &&
        value.source.trim() !== ''
      ) {
        sources.push(value.source)
      } else if (Array.isArray(value)) {
        sources.push(...getSourcesFromArray(value))
      } else {
        sources.push(...getSourcesFromObject(value))
      }
    }
  })

  return sources
}

// Function to add sources section to slide
const addSourcesSection = (slide, sources: string[]) => {
  if (!sources || sources.length === 0) return

  // Remove duplicates
  const uniqueSources = [...new Set(sources)]

  // Create a background for the sources section - moved to bottom
  slide.addShape('RECTANGLE', {
    x: 0.5,
    y: 6.8,
    w: 9.0,
    h: 0.8,
    fill: { color: 'F8FAFC' }, // Very light gray (slate-50)
    lineSize: 0,
  })

  // Add sources title
  slide.addText('Sources:', {
    x: 0.75,
    y: 6.85,
    fontSize: 8,
    bold: true,
    color: COLORS.secondaryText,
    fontFace: 'Arial',
  })

  // Split sources into two columns if there are more than 3
  const maxItemsPerColumn = 3
  const firstColumnSources = uniqueSources.slice(0, maxItemsPerColumn)
  const secondColumnSources = uniqueSources.slice(maxItemsPerColumn)

  // Add first column sources
  firstColumnSources.forEach((source, index) => {
    slide.addText(`• ${source}`, {
      x: 0.75,
      y: 7.0 + index * 0.15,
      fontSize: 7,
      color: COLORS.sourceText,
      fontFace: 'Arial',
      breakLine: true,
      w: 4.0,
    })
  })

  // Add second column sources if any
  if (secondColumnSources.length > 0) {
    secondColumnSources.forEach((source, index) => {
      slide.addText(`• ${source}`, {
        x: 5.0,
        y: 7.0 + index * 0.15,
        fontSize: 7,
        color: COLORS.sourceText,
        fontFace: 'Arial',
        breakLine: true,
        w: 4.0,
      })
    })
  }
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
const addInfoBlock = (
  slide,
  label,
  sourcedValue: SourcedValue<string> | undefined,
  x,
  y
) => {
  slide.addText(label, {
    x,
    y,
    fontSize: 14,
    bold: true,
    color: COLORS.titleText,
    fontFace: 'Arial',
  })

  slide.addText(getValue(sourcedValue) || 'N/A', {
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
const addListItems = (
  slide,
  title,
  items: SourcedValue<string>[] | undefined,
  x,
  y
) => {
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
    slide.addText(`• ${getValue(item)}`, {
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

// Better bullet point function using single text block
const addListItemsImproved = (
  slide,
  title,
  items: SourcedValue<string>[] | undefined,
  x,
  y,
  maxWidth = 4.0
) => {
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

  // Create single text block with all bullet points
  const bulletText = items
    .map(item => `• ${getValue(item)}`)
    .join('\n')

  slide.addText(bulletText, {
    x,
    y: y + 0.4,
    fontSize: 12,
    color: COLORS.secondaryText,
    fontFace: 'Arial',
    breakLine: true,
    w: maxWidth,
    valign: 'top',
  })
}

// Create slide for company profile
const createProfileSlide = (pptx, company: Company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Company Profile')
  addCardBackground(slide)

  // Company name with larger font
  slide.addText(company.name || 'Company Name', {
    x: 1.0,
    y: 1.5,
    fontSize: 20,
    bold: true,
    color: COLORS.primary,
    fontFace: 'Arial',
  })

  // Add company catchphrase
  if (company.profile?.catchphrase) {
    slide.addText(getValue(company.profile.catchphrase), {
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
  addInfoBlock(slide, 'Business Line', company.profile?.businessLine, 1.0, 2.7)
  addInfoBlock(slide, 'Website', company.website, 5.0, 2.7)
  addInfoBlock(
    slide,
    'Established',
    company.profile?.establishmentYear,
    1.0,
    3.7
  )
  addInfoBlock(slide, 'Employees', company.profile?.employeeCount, 5.0, 3.7)
  addInfoBlock(slide, 'Revenue', company.profile?.revenue, 1.0, 4.7)
  addInfoBlock(slide, 'CEO', company.profile?.ceo, 5.0, 4.7)
  addInfoBlock(slide, 'Headquarters', company.profile?.hq, 1.0, 5.7)

  // Add group name if available
  if (company.profile?.groupName) {
    addInfoBlock(slide, 'Group', company.profile.groupName, 5.0, 5.7)
  }

  // Add sources section
  const sources = getSourcesFromObject(company.profile)
  addSourcesSection(slide, sources)
}

// Create slide for products and services
const createProductsSlide = (pptx, company: Company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Products and Services')
  addCardBackground(slide)

  const products = company.products || {}

  // Add insights if available
  if (products.insights) {
    slide.addText('Insights:', {
      x: 1.0,
      y: 1.5,
      fontSize: 14,
      bold: true,
      color: COLORS.titleText,
      fontFace: 'Arial',
    })
    
    slide.addText(products.insights, {
      x: 1.0,
      y: 1.9,
      w: 8.0,
      fontSize: 12,
      color: COLORS.secondaryText,
      fontFace: 'Arial',
      breakLine: true,
    })
  }

  // Add product range in first column
  addListItemsImproved(slide, 'Product Range', products.range, 1.0, 2.8)

  // Add partner brands in second column
  addListItemsImproved(
    slide,
    'Partner Brands',
    products.partnerBrands,
    5.0,
    2.8
  )

  // Add private labels at bottom
  addListItemsImproved(
    slide,
    'Private Labels',
    products.privateLabels,
    1.0,
    4.5
  )

  // Add sources section
  const sources = getSourcesFromObject(company.products)
  addSourcesSection(slide, sources)
}

// Create slide for target audience and customer base
const createTargetAudienceSlide = (pptx, company: Company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Target Audience & Customer Base')
  addCardBackground(slide)

  const products = company.products || {}

  // Add customer type
  slide.addText('Customer Type', {
    x: 1.0,
    y: 1.5,
    fontSize: 14,
    bold: true,
    color: COLORS.titleText,
    fontFace: 'Arial',
  })

  slide.addText(products.customerType || 'N/A', {
    x: 1.0,
    y: 1.9,
    w: 8.0,
    fontSize: 12,
    color: COLORS.secondaryText,
    fontFace: 'Arial',
    breakLine: true,
  })

  // Add marketing positioning with more space
  slide.addText('Marketing Positioning', {
    x: 1.0,
    y: 2.5,
    fontSize: 14,
    bold: true,
    color: COLORS.titleText,
    fontFace: 'Arial',
  })

  slide.addText(products.marketingPositioning || 'N/A', {
    x: 1.0,
    y: 2.9,
    fontSize: 12,
    color: COLORS.secondaryText,
    fontFace: 'Arial',
    breakLine: true,
    w: 8.0,
    h: 3.0,
  })

  // Add sources section  
  const sources = getSourcesFromObject(company.products)
  addSourcesSection(slide, sources)
}

// Create slide for digital strategy
const createDigitalStrategySlide = (pptx, company: Company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Digital Strategy & Social Media')
  addCardBackground(slide)

  const digital = company.digital || {}

  // Add digital strategy
  slide.addText('Digital Strategy', {
    x: 1.0,
    y: 1.5,
    fontSize: 14,
    bold: true,
    color: COLORS.titleText,
    fontFace: 'Arial',
  })

  slide.addText(getValue(digital.strategy) || 'N/A', {
    x: 1.0,
    y: 1.9,
    fontSize: 12,
    color: COLORS.secondaryText,
    fontFace: 'Arial',
    breakLine: true,
    w: 4.0,
    h: 0.8,
  })

  // Add loyalty program
  slide.addText('Loyalty Program', {
    x: 1.0,
    y: 2.9,
    fontSize: 14,
    bold: true,
    color: COLORS.titleText,
    fontFace: 'Arial',
  })

  slide.addText(getValue(digital.loyaltyProgram) || 'N/A', {
    x: 1.0,
    y: 3.3,
    fontSize: 12,
    color: COLORS.secondaryText,
    fontFace: 'Arial',
    breakLine: true,
    w: 4.0,
    h: 0.8,
  })

  // Add online services in first column
  addListItemsImproved(
    slide,
    'Online Services',
    digital.onlineServices,
    1.0,
    4.3,
    4.0
  )

  // Add social media in second column
  if (digital.socialMedia && digital.socialMedia.length > 0) {
    slide.addText('Social Media Presence', {
      x: 5.0,
      y: 1.5,
      fontSize: 14,
      bold: true,
      color: COLORS.titleText,
      fontFace: 'Arial',
    })

    const socialMediaText = digital.socialMedia
      .map(social => `• ${social.name}`)
      .join('\n')

    slide.addText(socialMediaText, {
      x: 5.0,
      y: 1.9,
      fontSize: 12,
      color: COLORS.secondaryText,
      fontFace: 'Arial',
      breakLine: true,
      w: 4.0,
      valign: 'top',
    })
  }

  // Add sources section
  const digitalSources = getSourcesFromObject(company.digital)
  const socialSources = digital.socialMedia
    ? digital.socialMedia.flatMap((social) =>
        social.url?.source ? [social.url.source] : []
      )
    : []
  addSourcesSection(slide, [...digitalSources, ...socialSources])
}

// Create slide for CSR initiatives
const createCSRSlide = (pptx, company: Company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Corporate Social Responsibility')
  addCardBackground(slide)

  const csr = company.csr || {}

  // Add responsibility initiatives in first column
  addListItemsImproved(
    slide,
    'Responsibility Initiatives',
    csr.responsibility_initiatives,
    1.0,
    1.5,
    4.0
  )

  // Add charity actions in second column
  addListItemsImproved(slide, 'Charity Actions', csr.charity_actions, 5.0, 1.5, 4.0)

  // Add sources section
  const sources = getSourcesFromObject(company.csr)
  addSourcesSection(slide, sources)
}

// Create slide for recent news
const createNewsSlide = (pptx, company: Company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Press & Media')
  addCardBackground(slide)

  // Add press articles
  addListItemsImproved(slide, 'Press Articles', company.press?.articles, 1.0, 1.5, 8.0)

  // Add sources section
  const sources = getSourcesFromArray(company.press?.articles)
  addSourcesSection(slide, sources)
}


// Create title slide
const createTitleSlide = (pptx, company: Company) => {
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
  slide.addText(company.name || 'Company Overview', {
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
    slide.addText(getValue(company.profile.catchphrase), {
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

// Create timeline slide
const createTimelineSlide = (pptx, company: Company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Timeline')
  addCardBackground(slide)

  // Add timeline JSON dump for now as requested
  if (company.timeline) {
    slide.addText('Timeline Data:', {
      x: 1.0,
      y: 1.5,
      fontSize: 14,
      bold: true,
      color: COLORS.titleText,
      fontFace: 'Arial',
    })
    
    slide.addText(JSON.stringify(company.timeline, null, 2), {
      x: 1.0,
      y: 2.0,
      w: 8.0,
      h: 3.5,
      fontSize: 8,
      color: COLORS.secondaryText,
      fontFace: 'Courier New',
      valign: 'top',
      breakLine: true,
    })
  }

  // Add sources section
  const sources = company.timeline?.events 
    ? company.timeline.events.map(event => event.source).filter(source => source && source.trim() !== '')
    : []
  addSourcesSection(slide, sources)
}

// Create team slide
const createTeamSlide = (pptx, company: Company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Team & Management')
  addCardBackground(slide)

  // Add team JSON dump for now as requested
  if (company.team) {
    slide.addText('Team Data:', {
      x: 1.0,
      y: 1.5,
      fontSize: 14,
      bold: true,
      color: COLORS.titleText,
      fontFace: 'Arial',
    })
    
    slide.addText(JSON.stringify(company.team, null, 2), {
      x: 1.0,
      y: 2.0,
      w: 8.0,
      h: 3.5,
      fontSize: 8,
      color: COLORS.secondaryText,
      fontFace: 'Courier New',
      valign: 'top',
      breakLine: true,
    })
  }

  // Add sources section - team data doesn't have sources in the interface
  const sources: string[] = []
  addSourcesSection(slide, sources)
}

// Create jobs slide
const createJobsSlide = (pptx, company: Company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Job Opportunities')
  addCardBackground(slide)

  // Add jobs insights if available - COLUMN 1
  if (company.jobs?.insights) {
    slide.addText('Job Market Insights:', {
      x: 1.0,
      y: 1.5,
      fontSize: 14,
      bold: true,
      color: COLORS.titleText,
      fontFace: 'Arial',
    })
    
    // Combine insights into single text block
    const insights = company.jobs.insights
    const insightLines = []
    
    if (insights.total_openings) {
      insightLines.push(`Total Openings: ${getValue(insights.total_openings)}`)
    }
    
    if (insights.hiring_focus) {
      insightLines.push(`Hiring Focus: ${getValue(insights.hiring_focus)}`)
    }
    
    if (insights.top_departments) {
      const depts = getValue(insights.top_departments)
      if (Array.isArray(depts)) {
        insightLines.push(`Top Departments: ${depts.join(', ')}`)
      }
    }
    
    if (insights.growth_indicators) {
      insightLines.push(`Growth Indicators: ${getValue(insights.growth_indicators)}`)
    }
    
    if (insightLines.length > 0) {
      slide.addText(insightLines.join('\n'), {
        x: 1.0,
        y: 1.9,
        fontSize: 12,
        color: COLORS.secondaryText,
        fontFace: 'Arial',
        breakLine: true,
        w: 4.0,
        valign: 'top',
      })
    }
  }

  // Add job offers - COLUMN 2
  if (company.jobs?.offers && company.jobs.offers.length > 0) {
    slide.addText('Current Job Openings:', {
      x: 5.0,
      y: 1.5,
      fontSize: 14,
      bold: true,
      color: COLORS.titleText,
      fontFace: 'Arial',
    })
    
    const jobLines = company.jobs.offers
      .slice(0, 10) // Limit to first 10 jobs to avoid overcrowding
      .map(job => `${job.title} - ${job.location}`)
    
    slide.addText(jobLines.join('\n'), {
      x: 5.0,
      y: 1.9,
      fontSize: 11,
      color: COLORS.secondaryText,
      fontFace: 'Arial',
      breakLine: true,
      w: 4.0,
      valign: 'top',
    })
  }

  // Add sources section
  const sources = company.jobs?.insights 
    ? Object.values(company.jobs.insights)
        .filter(insight => insight && typeof insight === 'object' && 'source' in insight)
        .map(insight => (insight as any).source)
        .filter(source => source && source.trim() !== '')
    : []
  addSourcesSection(slide, sources)
}

// Create press slide
const createPressSlide = (pptx, company: Company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Press & Media')
  addCardBackground(slide)

  // Add press JSON dump for now as requested
  if (company.press) {
    slide.addText('Press Data:', {
      x: 1.0,
      y: 1.5,
      fontSize: 14,
      bold: true,
      color: COLORS.titleText,
      fontFace: 'Arial',
    })
    
    slide.addText(JSON.stringify(company.press, null, 2), {
      x: 1.0,
      y: 2.0,
      w: 8.0,
      h: 3.5,
      fontSize: 8,
      color: COLORS.secondaryText,
      fontFace: 'Courier New',
      valign: 'top',
      breakLine: true,
    })
  }

  // Add sources section
  const sources = getSourcesFromArray(company.press?.articles)
  addSourcesSection(slide, sources)
}

// Handle export with selected options
const handleExport = (selectedOptions: string[]) => {
  console.log('🚀 Export started with selected options:', selectedOptions)
  downloadPPT(selectedOptions)
  showModal.value = false
}

const downloadPPT = async (selectedOptions: string[] = []) => {
  if (!company.value) {
    console.log('❌ No company data available')
    return
  }

  console.log('📊 Company data available:', !!company.value)
  console.log('📋 Company data keys:', Object.keys(company.value))

  try {
    // Create new PowerPoint presentation
    const pptx = new pptxgen()
    pptx.layout = 'LAYOUT_WIDE'
    console.log('📄 PowerPoint presentation created')

    let slideCount = 0

    // Create slides based on selected options
    if (selectedOptions.includes('titleSlide')) {
      console.log('✅ Creating title slide')
      createTitleSlide(pptx, company.value as Company)
      slideCount++
    }


    if (selectedOptions.includes('profile')) {
      console.log('✅ Creating profile slide')
      createProfileSlide(pptx, company.value as Company)
      slideCount++
    }

    // Only create slides for sections that have data and are selected
    if (selectedOptions.includes('productsServices') && company.value.products) {
      console.log('✅ Creating products slide')
      createProductsSlide(pptx, company.value as Company)
      slideCount++
    } else if (selectedOptions.includes('productsServices')) {
      console.log('❌ Products selected but no data available')
    }

    if (selectedOptions.includes('targetAudience') && company.value.products && (company.value.products.customerType || company.value.products.marketingPositioning)) {
      console.log('✅ Creating target audience slide')
      createTargetAudienceSlide(pptx, company.value as Company)
      slideCount++
    } else if (selectedOptions.includes('targetAudience')) {
      console.log('❌ Target audience selected but no data available')
    }

    if (selectedOptions.includes('digitalStrategy') && company.value.digital) {
      console.log('✅ Creating digital strategy slide')
      createDigitalStrategySlide(pptx, company.value as Company)
      slideCount++
    } else if (selectedOptions.includes('digitalStrategy')) {
      console.log('❌ Digital strategy selected but no data available')
    }

    if (selectedOptions.includes('csr') && company.value.csr) {
      console.log('✅ Creating CSR slide')
      createCSRSlide(pptx, company.value as Company)
      slideCount++
    } else if (selectedOptions.includes('csr')) {
      console.log('❌ CSR selected but no data available')
    }

    if (selectedOptions.includes('news') && company.value.press && company.value.press.articles && company.value.press.articles.length > 0) {
      console.log('✅ Creating news slide')
      createNewsSlide(pptx, company.value as Company)
      slideCount++
    } else if (selectedOptions.includes('news')) {
      console.log('❌ News selected but no data available')
    }

    if (selectedOptions.includes('timeline') && company.value.timeline) {
      console.log('✅ Creating timeline slide')
      createTimelineSlide(pptx, company.value as Company)
      slideCount++
    } else if (selectedOptions.includes('timeline')) {
      console.log('❌ Timeline selected but no data available')
    }

    if (selectedOptions.includes('team') && company.value.team) {
      console.log('✅ Creating team slide')
      createTeamSlide(pptx, company.value as Company)
      slideCount++
    } else if (selectedOptions.includes('team')) {
      console.log('❌ Team selected but no data available')
    }

    if (selectedOptions.includes('jobs') && company.value.jobs) {
      console.log('✅ Creating jobs slide')
      createJobsSlide(pptx, company.value as Company)
      slideCount++
    } else if (selectedOptions.includes('jobs')) {
      console.log('❌ Jobs selected but no data available')
    }

    if (selectedOptions.includes('press') && company.value.press) {
      console.log('✅ Creating press slide')
      createPressSlide(pptx, company.value as Company)
      slideCount++
    } else if (selectedOptions.includes('press')) {
      console.log('❌ Press selected but no data available')
    }

    console.log(`📊 Total slides created: ${slideCount}`)

    // Generate filename from company name or use default
    const fileName = company.value.name
      ? `${company.value.name.replace(/[^a-z0-9]/gi, '_')}_Overview.pptx`
      : 'Company_Overview.pptx'

    console.log(`💾 Writing PowerPoint file: ${fileName}`)
    
    // Write file
    pptx.writeFile({ fileName })
    
    console.log(`✅ PowerPoint export completed successfully!`)
  } catch (error) {
    console.error('Error generating PowerPoint:', error)
    // Here you could add user notification about the error
  }
}
</script>
