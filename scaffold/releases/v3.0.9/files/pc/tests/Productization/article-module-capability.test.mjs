import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const pcRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..')
const repoRoot = resolve(pcRoot, '..')
const read = (path) => readFileSync(resolve(repoRoot, path), 'utf8')

const routeSource = read('server/route/app.php')
const pcIndex = read('pc/pages/index.vue')
const pcInformation = read('pc/pages/information/index.vue')
const pcCategory = read('pc/pages/information/[source].vue')
const pcCollection = read('pc/pages/user/collection.vue')

for (const route of [
  "Route::get('api/pc/index'",
  "Route::get('api/pc/infoCenter'",
  "Route::get('api/pc/articleDetail'",
  "Route::get('api/article/lists'",
  "Route::get('api/article/detail'",
]) {
  assert.equal(routeSource.includes(route), true, `missing PC Article route: ${route}`)
}
assert.equal(
  routeSource.match(/->middleware\(PublicArticleTenantMiddleware::class/g)?.length >= 7,
  true,
  'PC and public Article routes are not uniformly Module guarded',
)

assert.equal(pcIndex.includes("/api/pc/index"), true, 'PC home bypassed the guarded aggregate API')
assert.equal(pcInformation.includes("/api/article/cate") && pcInformation.includes("/api/article/lists"), true, 'PC information page bypassed guarded Article APIs')
assert.equal(pcCategory.includes("/api/article/cate") && pcCategory.includes("/api/article/lists"), true, 'PC category deep-link bypassed guarded Article APIs')
assert.equal(pcCollection.includes("/api/article/collect"), true, 'PC member collection bypassed the Article collection API')

// A disabled module must never leave server-fetched Article data rendered from stale state.
assert.equal(pcIndex.includes('indexData.value?.data?.all || indexData.value?.data?.article || []'), true, 'PC home lacks an empty fail-closed Article fallback')
assert.equal(pcCollection.includes('res.data?.lists || []'), true, 'PC member collection lacks an empty fail-closed Article fallback')

console.log('PC-ARTICLE-MODULE-CAPABILITY-001 passed')
