import { h, type Component } from 'vue'
import DefaultTheme from 'vitepress/theme'
import HomeTechVisual from './HomeTechVisual.vue'
import NotFound from './NotFound.vue'
import './custom.css'

const optionalComponents = import.meta.glob('./ProductStatus.vue', {
  eager: true,
  import: 'default',
}) as Record<string, Component>
const ProductStatus = optionalComponents['./ProductStatus.vue']

export default {
  extends: DefaultTheme,
  Layout: () => h(DefaultTheme.Layout!, null, {
    'home-hero-image': () => h(HomeTechVisual),
    'not-found': () => h(NotFound),
  }),
  enhanceApp({ app }) {
    if (ProductStatus) app.component('ProductStatus', ProductStatus)
  },
}
