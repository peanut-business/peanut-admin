import { h } from 'vue'
import DefaultTheme from 'vitepress/theme'
import HomeTechVisual from './HomeTechVisual.vue'
import NotFound from './NotFound.vue'
import './custom.css'

export default {
  extends: DefaultTheme,
  Layout: () => h(DefaultTheme.Layout!, null, {
    'home-hero-image': () => h(HomeTechVisual),
    'not-found': () => h(NotFound),
  }),
}
