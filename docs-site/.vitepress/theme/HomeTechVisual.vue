<template>
  <div class="tech-visual" aria-label="Peanut Admin 技术架构视觉">
    <div class="tech-visual__halo"></div>
    <div class="tech-visual__orbit tech-visual__orbit--outer"></div>
    <div class="tech-visual__orbit tech-visual__orbit--inner"></div>
    <div class="tech-visual__axis tech-visual__axis--x"></div>
    <div class="tech-visual__axis tech-visual__axis--y"></div>
    <div class="tech-visual__core">
      <img src="/brand/logo.svg" alt="Peanut Admin" />
      <span class="tech-visual__pulse"></span>
    </div>
    <span class="tech-visual__chip tech-visual__chip--top">APP OWNED</span>
    <span class="tech-visual__chip tech-visual__chip--right">SCHEMA SAFE</span>
    <span class="tech-visual__chip tech-visual__chip--bottom">RECOVERABLE</span>
    <span class="tech-visual__node tech-visual__node--a"></span>
    <span class="tech-visual__node tech-visual__node--b"></span>
    <span class="tech-visual__node tech-visual__node--c"></span>
    <div class="tech-visual__status"><i></i> APPLICATION SYSTEM ONLINE</div>
  </div>
</template>

<style scoped>
.tech-visual {
  position: relative;
  width: 430px;
  height: 430px;
  isolation: isolate;
}

.tech-visual::before {
  position: absolute;
  inset: 28px;
  border-radius: 44px;
  background:
    linear-gradient(rgb(36 87 230 / 7%) 1px, transparent 1px),
    linear-gradient(90deg, rgb(36 87 230 / 7%) 1px, transparent 1px);
  background-size: 22px 22px;
  content: "";
  mask-image: radial-gradient(circle, black 25%, transparent 72%);
  transform: perspective(500px) rotateX(58deg) rotateZ(-12deg) translateY(80px);
}

.tech-visual__halo {
  position: absolute;
  inset: 42px;
  border-radius: 50%;
  background: radial-gradient(circle, rgb(36 87 230 / 24%), rgb(50 201 140 / 8%) 52%, transparent 72%);
  filter: blur(14px);
}

.tech-visual__orbit {
  position: absolute;
  border: 1px solid rgb(36 87 230 / 22%);
  border-radius: 50%;
}

.tech-visual__orbit::before,
.tech-visual__orbit::after {
  position: absolute;
  width: 7px;
  height: 7px;
  border: 3px solid var(--vp-c-bg);
  border-radius: 50%;
  background: #32c98c;
  box-shadow: 0 0 18px rgb(50 201 140 / 70%);
  content: "";
}

.tech-visual__orbit--outer {
  inset: 26px;
  border-style: dashed;
  animation: tech-spin 26s linear infinite;
}

.tech-visual__orbit--outer::before {
  top: 55px;
  right: 12px;
}

.tech-visual__orbit--outer::after {
  bottom: 46px;
  left: 18px;
  background: #4f7dff;
  box-shadow: 0 0 18px rgb(79 125 255 / 70%);
}

.tech-visual__orbit--inner {
  inset: 72px;
  border-color: rgb(50 201 140 / 18%);
  animation: tech-spin-reverse 18s linear infinite;
}

.tech-visual__orbit--inner::before {
  top: -5px;
  left: 50%;
}

.tech-visual__orbit--inner::after {
  right: -5px;
  bottom: 32%;
  background: #4f7dff;
}

.tech-visual__axis {
  position: absolute;
  z-index: -1;
  background: linear-gradient(90deg, transparent, rgb(36 87 230 / 18%), transparent);
}

.tech-visual__axis--x {
  top: 50%;
  left: 0;
  width: 100%;
  height: 1px;
}

.tech-visual__axis--y {
  top: 0;
  left: 50%;
  width: 1px;
  height: 100%;
  background: linear-gradient(transparent, rgb(36 87 230 / 18%), transparent);
}

.tech-visual__core {
  position: absolute;
  top: 50%;
  left: 50%;
  display: grid;
  width: 236px;
  height: 236px;
  place-items: center;
  border: 1px solid rgb(255 255 255 / 68%);
  border-radius: 64px;
  background: rgb(255 255 255 / 16%);
  box-shadow:
    0 34px 70px rgb(25 61 161 / 28%),
    0 0 0 10px rgb(36 87 230 / 5%),
    0 0 0 26px rgb(36 87 230 / 3%);
  backdrop-filter: blur(12px);
  transform: translate(-50%, -50%) rotate(-2deg);
}

.dark .tech-visual__core {
  border-color: rgb(255 255 255 / 12%);
  background: rgb(10 19 41 / 44%);
}

.tech-visual__core img {
  width: 210px;
  height: 210px;
  filter: drop-shadow(0 24px 32px rgb(25 61 161 / 28%));
}

.tech-visual__pulse {
  position: absolute;
  right: 24px;
  bottom: 24px;
  width: 13px;
  height: 13px;
  border: 3px solid var(--vp-c-bg);
  border-radius: 50%;
  background: #32c98c;
  box-shadow: 0 0 0 0 rgb(50 201 140 / 45%);
  animation: tech-pulse 2.4s ease-out infinite;
}

.tech-visual__chip {
  position: absolute;
  z-index: 2;
  padding: 7px 10px;
  border: 1px solid rgb(36 87 230 / 18%);
  border-radius: 8px;
  color: var(--vp-c-brand-1);
  background: color-mix(in srgb, var(--vp-c-bg) 80%, transparent);
  box-shadow: 0 10px 28px rgb(31 55 108 / 10%);
  backdrop-filter: blur(12px);
  font-size: 9px;
  font-weight: 760;
  letter-spacing: 0.12em;
}

.tech-visual__chip--top {
  top: 26px;
  left: 72px;
}

.tech-visual__chip--right {
  top: 112px;
  right: 4px;
}

.tech-visual__chip--bottom {
  bottom: 64px;
  left: 12px;
}

.tech-visual__node {
  position: absolute;
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: var(--vp-c-brand-1);
  box-shadow: 0 0 14px var(--vp-c-brand-1);
}

.tech-visual__node--a { top: 72px; right: 94px; }
.tech-visual__node--b { right: 64px; bottom: 106px; }
.tech-visual__node--c { bottom: 34px; left: 146px; }

.tech-visual__status {
  position: absolute;
  right: 44px;
  bottom: 14px;
  display: flex;
  gap: 7px;
  align-items: center;
  color: var(--vp-c-text-3);
  font-size: 8px;
  font-weight: 700;
  letter-spacing: 0.11em;
}

.tech-visual__status i {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #32c98c;
  box-shadow: 0 0 10px rgb(50 201 140 / 75%);
}

@keyframes tech-spin {
  to { transform: rotate(360deg); }
}

@keyframes tech-spin-reverse {
  to { transform: rotate(-360deg); }
}

@keyframes tech-pulse {
  70% { box-shadow: 0 0 0 14px rgb(50 201 140 / 0%); }
  100% { box-shadow: 0 0 0 0 rgb(50 201 140 / 0%); }
}

@media (max-width: 960px) {
  .tech-visual {
    width: 340px;
    height: 340px;
    transform: scale(0.88);
  }
}

@media (max-width: 720px) {
  .tech-visual {
    width: 270px;
    height: 270px;
    transform: scale(0.78);
  }

  .tech-visual__chip--right {
    right: -18px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .tech-visual__orbit,
  .tech-visual__pulse {
    animation: none;
  }
}
</style>
