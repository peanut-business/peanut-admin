<script setup lang="ts">
import { computed, ref } from 'vue'
import ledger from '../../../docs/product-status/capability-ledger.json'

type Capability = (typeof ledger.capabilities)[number]

const filters = [
  { id: 'all', label: '全部' },
  { id: 'verified', label: '已验证' },
  { id: 'active', label: '推进中' },
  { id: 'planned', label: '计划 / 受阻' },
  { id: 'deferred', label: '暂缓 / 范围外' },
] as const

const activeFilter = ref<(typeof filters)[number]['id']>('all')

const statusMeta: Record<string, { label: string; tone: string }> = {
  verified: { label: '已验证', tone: 'success' },
  implemented: { label: '已实现，待验收', tone: 'active' },
  in_progress: { label: '进行中', tone: 'active' },
  planned: { label: '计划中', tone: 'planned' },
  blocked: { label: '受阻', tone: 'blocked' },
  deferred: { label: '暂缓', tone: 'muted' },
  out_of_scope: { label: '范围外', tone: 'muted' },
  retired: { label: '已退出', tone: 'muted' },
}

const matchesFilter = (capability: Capability) => {
  if (activeFilter.value === 'all') return true
  if (activeFilter.value === 'verified') return capability.status === 'verified'
  if (activeFilter.value === 'active') return ['implemented', 'in_progress'].includes(capability.status)
  if (activeFilter.value === 'planned') return ['planned', 'blocked'].includes(capability.status)
  return ['deferred', 'out_of_scope', 'retired'].includes(capability.status)
}

const visibleCapabilities = computed(() => ledger.capabilities.filter(matchesFilter))
const count = (statuses: string[]) => ledger.capabilities.filter(item => statuses.includes(item.status)).length
const shortCommit = ledger.facts_baseline.commit.slice(0, 10)
</script>

<template>
  <section class="status-console">
    <header class="status-console__hero">
      <div>
        <div class="status-console__signal"><i></i> PRODUCT DELIVERY TELEMETRY</div>
        <h1>产品能力与交付状态</h1>
        <p>{{ ledger.overall.summary }}</p>
      </div>
      <div class="status-console__baseline">
        <span>FACTS BASELINE</span>
        <strong>{{ ledger.facts_baseline.branch }}@{{ shortCommit }}</strong>
        <small>{{ ledger.facts_baseline.reviewed_at }}</small>
      </div>
    </header>

    <div class="status-console__metrics">
      <article>
        <span>VERIFIED</span>
        <strong>{{ count(['verified']) }}</strong>
        <small>已完成能力验收</small>
      </article>
      <article>
        <span>ACTIVE</span>
        <strong>{{ count(['implemented', 'in_progress']) }}</strong>
        <small>实现或验收进行中</small>
      </article>
      <article>
        <span>QUEUED</span>
        <strong>{{ count(['planned', 'blocked']) }}</strong>
        <small>计划中或等待解除</small>
      </article>
      <article>
        <span>BOUNDARY</span>
        <strong>{{ count(['deferred', 'out_of_scope', 'retired']) }}</strong>
        <small>暂缓、范围外或退出</small>
      </article>
    </div>

    <div class="status-console__toolbar" aria-label="状态筛选">
      <button
        v-for="filter in filters"
        :key="filter.id"
        type="button"
        :class="{ active: activeFilter === filter.id }"
        @click="activeFilter = filter.id"
      >
        {{ filter.label }}
      </button>
    </div>

    <div class="status-console__grid">
      <article v-for="capability in visibleCapabilities" :key="capability.id" class="status-card">
        <div class="status-card__topline">
          <code>{{ capability.id }}</code>
          <span :class="`status-badge status-badge--${statusMeta[capability.status].tone}`">
            {{ statusMeta[capability.status].label }}
          </span>
        </div>
        <p class="status-card__area">{{ capability.area.toUpperCase() }}</p>
        <h2>{{ capability.name }}</h2>
        <p>{{ capability.summary }}</p>
        <details>
          <summary>查看验收条件</summary>
          <ul>
            <li v-for="item in capability.acceptance" :key="item">{{ item }}</li>
          </ul>
        </details>
      </article>
    </div>

    <footer class="status-console__footer">
      <span>数据来自版本化能力账本，页面构建时自动同步。</span>
      <a href="/releases">查看正式版本记录 →</a>
    </footer>
  </section>
</template>

<style scoped>
.status-console {
  --console-line: color-mix(in srgb, var(--vp-c-divider) 72%, var(--vp-c-brand-1));
  width: min(1180px, calc(100vw - 48px));
  margin: 24px 50% 80px;
  transform: translateX(-50%);
}

.status-console__hero {
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 48px;
  align-items: end;
  overflow: hidden;
  padding: 48px;
  border: 1px solid var(--console-line);
  border-radius: 28px;
  background:
    linear-gradient(rgb(36 87 230 / 5%) 1px, transparent 1px),
    linear-gradient(90deg, rgb(36 87 230 / 5%) 1px, transparent 1px),
    radial-gradient(circle at 90% 0, rgb(50 201 140 / 18%), transparent 25rem),
    color-mix(in srgb, var(--vp-c-bg) 92%, var(--vp-c-brand-soft));
  background-size: 32px 32px, 32px 32px, auto, auto;
  box-shadow: 0 24px 70px rgb(31 55 108 / 10%);
}

.status-console__signal {
  display: flex;
  gap: 9px;
  align-items: center;
  margin-bottom: 18px;
  color: var(--vp-c-brand-1);
  font-size: 10px;
  font-weight: 780;
  letter-spacing: 0.14em;
}

.status-console__signal i {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #32c98c;
  box-shadow: 0 0 14px rgb(50 201 140 / 75%);
}

.status-console h1 {
  max-width: 760px;
  margin: 0;
  color: var(--vp-c-text-1);
  font-size: clamp(38px, 6vw, 64px);
  font-weight: 820;
  letter-spacing: -0.055em;
  line-height: 1.05;
}

.status-console__hero p {
  max-width: 760px;
  margin: 22px 0 0;
  color: var(--vp-c-text-2);
  font-size: 16px;
  line-height: 1.85;
}

.status-console__baseline {
  display: flex;
  min-width: 220px;
  flex-direction: column;
  gap: 7px;
  padding: 18px;
  border: 1px solid var(--console-line);
  border-radius: 14px;
  background: color-mix(in srgb, var(--vp-c-bg) 80%, transparent);
  font-family: var(--vp-font-family-mono);
}

.status-console__baseline span,
.status-console__baseline small {
  color: var(--vp-c-text-3);
  font-size: 9px;
  letter-spacing: 0.11em;
}

.status-console__baseline strong {
  color: var(--vp-c-text-1);
  font-size: 12px;
}

.status-console__metrics {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin: 18px 0 42px;
}

.status-console__metrics article {
  display: grid;
  min-height: 134px;
  grid-template-columns: 1fr auto;
  gap: 14px;
  align-items: start;
  padding: 22px;
  border: 1px solid var(--console-line);
  border-radius: 17px;
  background: color-mix(in srgb, var(--vp-c-bg-soft) 60%, transparent);
}

.status-console__metrics span,
.status-console__metrics small {
  color: var(--vp-c-text-3);
  font-size: 9px;
  font-weight: 720;
  letter-spacing: 0.12em;
}

.status-console__metrics strong {
  grid-row: span 2;
  color: var(--vp-c-brand-1);
  font-size: 38px;
  line-height: 1;
}

.status-console__metrics small {
  align-self: end;
  letter-spacing: 0;
}

.status-console__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 20px;
}

.status-console__toolbar button {
  padding: 8px 14px;
  border: 1px solid var(--vp-c-divider);
  border-radius: 999px;
  color: var(--vp-c-text-2);
  background: var(--vp-c-bg);
  cursor: pointer;
  font: inherit;
  font-size: 12px;
  font-weight: 650;
  transition: border-color 0.2s ease, color 0.2s ease, background 0.2s ease;
}

.status-console__toolbar button:hover,
.status-console__toolbar button.active {
  border-color: var(--vp-c-brand-1);
  color: #fff;
  background: var(--vp-c-brand-1);
}

.status-console__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.status-card {
  position: relative;
  min-height: 250px;
  padding: 26px;
  overflow: hidden;
  border: 1px solid var(--console-line);
  border-radius: 20px;
  background: color-mix(in srgb, var(--vp-c-bg) 92%, var(--vp-c-brand-soft));
  transition: border-color 0.22s ease, box-shadow 0.22s ease, transform 0.22s ease;
}

.status-card::before {
  position: absolute;
  top: 0;
  left: 26px;
  width: 74px;
  height: 2px;
  background: linear-gradient(90deg, var(--vp-c-brand-1), #32c98c);
  content: "";
}

.status-card:hover {
  border-color: rgb(36 87 230 / 34%);
  box-shadow: 0 20px 50px rgb(31 55 108 / 10%);
  transform: translateY(-3px);
}

.status-card__topline {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: center;
}

.status-card__topline code {
  color: var(--vp-c-text-3);
  background: transparent;
  font-size: 10px;
  letter-spacing: 0.07em;
}

.status-badge {
  padding: 5px 8px;
  border: 1px solid currentColor;
  border-radius: 999px;
  font-size: 9px;
  font-weight: 720;
}

.status-badge--success { color: #07885a; background: rgb(50 201 140 / 9%); }
.status-badge--active { color: var(--vp-c-brand-1); background: var(--vp-c-brand-soft); }
.status-badge--planned { color: #8a6411; background: rgb(234 179 8 / 9%); }
.status-badge--blocked { color: #c24155; background: rgb(244 63 94 / 8%); }
.status-badge--muted { color: var(--vp-c-text-3); background: var(--vp-c-bg-soft); }

.status-card__area {
  margin: 24px 0 6px !important;
  color: var(--vp-c-brand-1) !important;
  font-size: 9px !important;
  font-weight: 760;
  letter-spacing: 0.12em;
}

.status-card h2 {
  margin: 0;
  padding: 0;
  border: 0;
  color: var(--vp-c-text-1);
  font-size: 21px;
  font-weight: 720;
  letter-spacing: -0.025em;
}

.status-card > p:not(.status-card__area) {
  margin: 12px 0 0;
  color: var(--vp-c-text-2);
  font-size: 13px;
  line-height: 1.75;
}

.status-card details {
  margin-top: 20px;
  border-top: 1px solid var(--vp-c-divider);
  padding-top: 14px;
}

.status-card summary {
  color: var(--vp-c-brand-1);
  cursor: pointer;
  font-size: 11px;
  font-weight: 680;
}

.status-card ul {
  margin: 12px 0 0;
  padding-left: 18px;
  color: var(--vp-c-text-2);
  font-size: 12px;
  line-height: 1.7;
}

.status-console__footer {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid var(--vp-c-divider);
  color: var(--vp-c-text-3);
  font-size: 11px;
}

.status-console__footer a {
  color: var(--vp-c-brand-1);
  font-weight: 680;
  text-decoration: none;
}

@media (max-width: 860px) {
  .status-console__hero {
    grid-template-columns: 1fr;
  }

  .status-console__metrics {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .status-console {
    width: calc(100vw - 32px);
    margin-top: 8px;
  }

  .status-console__hero {
    gap: 28px;
    padding: 28px;
    border-radius: 22px;
  }

  .status-console h1 {
    font-size: 40px;
  }

  .status-console__baseline {
    min-width: 0;
  }

  .status-console__metrics,
  .status-console__grid {
    grid-template-columns: 1fr;
  }

  .status-console__footer {
    flex-direction: column;
  }
}
</style>
