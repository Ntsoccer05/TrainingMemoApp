<template>
  <div class="flex justify-center items-center py-8">
    <div class="breathing-ring" role="status" aria-label="読み込み中"></div>
  </div>
</template>

<script setup lang="ts"></script>

<style scoped>
.breathing-ring {
  width: 32px;
  height: 32px;
  border-radius: 9999px;
  border: 3px solid #ccfbf1; /* teal-100 */
  border-top-color: #0d9488; /* teal-600 */
  animation: breathing-ring-spin 1.4s cubic-bezier(0.45, 0, 0.55, 1) infinite;
}

@keyframes breathing-ring-spin {
  0% {
    transform: rotate(0deg) scale(0.92);
    opacity: 0.7;
  }
  50% {
    transform: rotate(180deg) scale(1.04);
    opacity: 1;
  }
  100% {
    transform: rotate(360deg) scale(0.92);
    opacity: 0.7;
  }
}

@media (prefers-reduced-motion: reduce) {
  .breathing-ring {
    animation: none;
  }
}
</style>
