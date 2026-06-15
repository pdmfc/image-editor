export const GALLERY_FOLDER_COLORS = [
  '#6366F1',
  '#3B82F6',
  '#06B6D4',
  '#10B981',
  '#84CC16',
  '#EAB308',
  '#F97316',
  '#EF4444',
  '#EC4899',
  '#8B5CF6'
]

export const GALLERY_FOLDER_SYSTEM_COLOR = '#94A3B8'

export function defaultFolderColor(folders = []) {
  const userCount = folders.filter((folder) => !folder.system).length
  return GALLERY_FOLDER_COLORS[userCount % GALLERY_FOLDER_COLORS.length]
}

export function folderDisplayColor(folder) {
  if (folder?.color) {
    return folder.color
  }
  if (folder?.system) {
    return GALLERY_FOLDER_SYSTEM_COLOR
  }
  return '#9CA3AF'
}

function hexToRgb(hex) {
  const normalized = String(hex).replace('#', '')
  if (normalized.length !== 6) {
    return { r: 148, g: 163, b: 184 }
  }

  return {
    r: Number.parseInt(normalized.slice(0, 2), 16),
    g: Number.parseInt(normalized.slice(2, 4), 16),
    b: Number.parseInt(normalized.slice(4, 6), 16)
  }
}

function darken(rgb, amount = 48) {
  return {
    r: Math.max(0, rgb.r - amount),
    g: Math.max(0, rgb.g - amount),
    b: Math.max(0, rgb.b - amount)
  }
}

export function folderTintStyles(folder, { active = false, dropTarget = false } = {}) {
  const color = folderDisplayColor(folder)
  const rgb = hexToRgb(color)
  const { r, g, b } = rgb
  const dark = darken(rgb)

  if (dropTarget) {
    return {
      borderColor: color,
      backgroundColor: `rgba(${r}, ${g}, ${b}, 0.2)`,
      boxShadow: `0 0 0 2px rgba(${r}, ${g}, ${b}, 0.28)`,
      color: `rgb(${dark.r}, ${dark.g}, ${dark.b})`
    }
  }

  if (active) {
    return {
      borderColor: color,
      backgroundColor: `rgba(${r}, ${g}, ${b}, 0.14)`,
      boxShadow: `0 0 0 2px rgba(${r}, ${g}, ${b}, 0.2)`,
      color: `rgb(${dark.r}, ${dark.g}, ${dark.b})`,
      fontWeight: '500'
    }
  }

  return {
    borderColor: `rgba(${r}, ${g}, ${b}, 0.34)`
  }
}

export function folderCountBadgeStyle(folder, { active = false } = {}) {
  const color = folderDisplayColor(folder)
  const { r, g, b } = hexToRgb(color)

  return {
    backgroundColor: `rgba(${r}, ${g}, ${b}, ${active ? 0.22 : 0.12})`,
    color
  }
}
