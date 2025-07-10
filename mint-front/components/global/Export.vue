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
  slide.addText(company.name?.toUpperCase() || 'COMPANY NAME', {
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

  addSlideTitle(slide, 'Products and Services (1/2)')
  addCardBackground(slide)

  const products = company.products || {}

  // COLUMN 1: Insights
  if (products.insights) {
    slide.addText('Product Insights:', {
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
      w: 4.0,
      fontSize: 12,
      color: COLORS.secondaryText,
      fontFace: 'Arial',
      breakLine: true,
      valign: 'top',
    })
  }

  // COLUMN 2: Product Range
  addListItemsImproved(slide, 'Product Range', products.range, 5.0, 1.5, 4.0)

  // Add sources section
  const sources = getSourcesFromObject(company.products)
  addSourcesSection(slide, sources)
}

// Create second products slide for partner brands and private labels
const createProductsSlide2 = (pptx, company: Company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Products and Services (2/2)')
  addCardBackground(slide)

  const products = company.products || {}

  // COLUMN 1: Partner Brands
  addListItemsImproved(
    slide,
    'Partner Brands',
    products.partnerBrands,
    1.0,
    1.5,
    4.0
  )

  // COLUMN 2: Private Labels
  addListItemsImproved(
    slide,
    'Private Labels',
    products.privateLabels,
    5.0,
    1.5,
    4.0
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

  // COLUMN 1: Customer Type
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
    w: 4.0,
    fontSize: 12,
    color: COLORS.secondaryText,
    fontFace: 'Arial',
    breakLine: true,
    valign: 'top',
  })

  // COLUMN 2: Marketing Positioning
  slide.addText('Marketing Positioning', {
    x: 5.0,
    y: 1.5,
    fontSize: 14,
    bold: true,
    color: COLORS.titleText,
    fontFace: 'Arial',
  })

  slide.addText(products.marketingPositioning || 'N/A', {
    x: 5.0,
    y: 1.9,
    fontSize: 12,
    color: COLORS.secondaryText,
    fontFace: 'Arial',
    breakLine: true,
    w: 4.0,
    valign: 'top',
  })

  // Add sources section  
  const sources = getSourcesFromObject(company.products)
  addSourcesSection(slide, sources)
}

// Create slide for digital strategy
const createDigitalStrategySlide = (pptx, company: Company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Digital Strategy & Social Media (1/2)')
  addCardBackground(slide)

  const digital = company.digital || {}

  // COLUMN 1: Digital Strategy
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
    valign: 'top',
  })

  // COLUMN 2: Loyalty Program
  slide.addText('Loyalty Program', {
    x: 5.0,
    y: 1.5,
    fontSize: 14,
    bold: true,
    color: COLORS.titleText,
    fontFace: 'Arial',
  })

  slide.addText(getValue(digital.loyaltyProgram) || 'N/A', {
    x: 5.0,
    y: 1.9,
    fontSize: 12,
    color: COLORS.secondaryText,
    fontFace: 'Arial',
    breakLine: true,
    w: 4.0,
    valign: 'top',
  })

  // Add sources section
  const digitalSources = getSourcesFromObject(company.digital)
  const socialSources = digital.socialMedia
    ? digital.socialMedia.flatMap((social) =>
        social.url?.source ? [social.url.source] : []
      )
    : []
  addSourcesSection(slide, digitalSources)
}

// Create second digital strategy slide for online services and social media
const createDigitalStrategySlide2 = (pptx, company: Company) => {
  const slide = pptx.addSlide()
  slide.background = { color: COLORS.background }

  addSlideTitle(slide, 'Digital Strategy & Social Media (2/2)')
  addCardBackground(slide)

  const digital = company.digital || {}

  // COLUMN 1: Online Services
  addListItemsImproved(
    slide,
    'Online Services',
    digital.onlineServices,
    1.0,
    1.5,
    4.0
  )

  // COLUMN 2: Social Media
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
  const socialSources = digital.socialMedia
    ? digital.socialMedia.flatMap((social) =>
        social.url?.source ? [social.url.source] : []
      )
    : []
  addSourcesSection(slide, socialSources)
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
  slide.addText(company.name?.toUpperCase() || 'COMPANY OVERVIEW', {
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


  // Add description text about MINT software
  slide.addText('This company screening is generated from the screening module\nof the MINT software by ChapsVision', {
    x: 0.5,
    y: 4.2,
    w: 9.0,
    fontSize: 14,
    color: COLORS.secondaryText,
    fontFace: 'Arial',
    align: 'center',
    breakLine: true,
  })

  // Add date at bottom
  const currentDate = new Date().toLocaleDateString()
  slide.addText(`Generated on ${currentDate}`, {
    x: 0.5,
    y: 5.2,
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

  // Add timeline insights - COLUMN 1
  if (company.timeline?.insights) {
    slide.addText('Timeline Insights:', {
      x: 1.0,
      y: 1.5,
      fontSize: 14,
      bold: true,
      color: COLORS.titleText,
      fontFace: 'Arial',
    })
    
    slide.addText(company.timeline.insights, {
      x: 1.0,
      y: 1.9,
      w: 4.0,
      h: 4.5,
      fontSize: 12,
      color: COLORS.secondaryText,
      fontFace: 'Arial',
      valign: 'top',
      breakLine: true,
    })
  }

  // Add timeline events - COLUMN 2
  if (company.timeline?.events && company.timeline.events.length > 0) {
    slide.addText('Key Events:', {
      x: 5.0,
      y: 1.5,
      fontSize: 14,
      bold: true,
      color: COLORS.titleText,
      fontFace: 'Arial',
    })
    
    const eventLines = company.timeline.events
      .slice(0, 15) // Limit to first 15 events
      .map(event => `${event.date} - ${event.title}`)
    
    slide.addText(eventLines.join('\n'), {
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

  // Add team members across both columns
  if (company.team && company.team.length > 0) {
    slide.addText('Leadership Team:', {
      x: 1.0,
      y: 1.5,
      fontSize: 14,
      bold: true,
      color: COLORS.titleText,
      fontFace: 'Arial',
    })
    
    // Function to format team member with subordinates
    const formatTeamMember = (member, indent = '') => {
      const fullName = `${member.firstName} ${member.lastName}`
      let result = `${indent}${fullName} - ${member.position}`
      
      if (member.subordinates && member.subordinates.length > 0) {
        const subordinateLines = member.subordinates
          .map(sub => formatTeamMember(sub, '  '))
          .join('\n')
        result += '\n' + subordinateLines
      }
      
      return result
    }
    
    const teamLines = company.team
      .slice(0, 20) // Limit to avoid overcrowding
      .map(member => formatTeamMember(member))
    
    // Split team members between two columns
    const midPoint = Math.ceil(teamLines.length / 2)
    const column1 = teamLines.slice(0, midPoint)
    const column2 = teamLines.slice(midPoint)
    
    // Column 1
    if (column1.length > 0) {
      slide.addText(column1.join('\n'), {
        x: 1.0,
        y: 1.9,
        fontSize: 11,
        color: COLORS.secondaryText,
        fontFace: 'Arial',
        breakLine: true,
        w: 4.0,
        valign: 'top',
      })
    }
    
    // Column 2
    if (column2.length > 0) {
      slide.addText(column2.join('\n'), {
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

  // Add press articles in 2-column layout
  if (company.press?.articles && company.press.articles.length > 0) {
    slide.addText('Press Articles:', {
      x: 1.0,
      y: 1.5,
      fontSize: 14,
      bold: true,
      color: COLORS.titleText,
      fontFace: 'Arial',
    })
    
    const articleValues = company.press.articles
      .map(article => getValue(article))
      .filter(value => value && value.trim() !== '')
      .slice(0, 20) // Limit to avoid overcrowding
    
    // Split articles between two columns
    const midPoint = Math.ceil(articleValues.length / 2)
    const column1 = articleValues.slice(0, midPoint)
    const column2 = articleValues.slice(midPoint)
    
    // Column 1
    if (column1.length > 0) {
      slide.addText(column1.map(article => `• ${article}`).join('\n'), {
        x: 1.0,
        y: 1.9,
        fontSize: 11,
        color: COLORS.secondaryText,
        fontFace: 'Arial',
        breakLine: true,
        w: 4.0,
        valign: 'top',
      })
    }
    
    // Column 2
    if (column2.length > 0) {
      slide.addText(column2.map(article => `• ${article}`).join('\n'), {
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
      console.log('✅ Creating products slide 1/2')
      createProductsSlide(pptx, company.value as Company)
      slideCount++
      
      // Add second products slide if there are partner brands or private labels
      if (company.value.products.partnerBrands || company.value.products.privateLabels) {
        console.log('✅ Creating products slide 2/2')
        createProductsSlide2(pptx, company.value as Company)
        slideCount++
      }
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
      console.log('✅ Creating digital strategy slide 1/2')
      createDigitalStrategySlide(pptx, company.value as Company)
      slideCount++
      
      // Add second digital strategy slide if there are online services or social media
      if (company.value.digital.onlineServices || company.value.digital.socialMedia) {
        console.log('✅ Creating digital strategy slide 2/2')
        createDigitalStrategySlide2(pptx, company.value as Company)
        slideCount++
      }
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
