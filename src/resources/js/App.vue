<template>
  <div class="m-0 p-0 h-screen">
    <div v-if="isMaintenance" class="flex h-full items-center justify-center text-center p-8">
      <div>
        <p class="text-xl font-bold mb-2">ただいまメンテナンス中です</p>
        <p class="text-gray-600">毎日 1:00〜4:00 の間はメンテナンスのためご利用いただけません。<br />しばらく経ってから再度アクセスしてください。</p>
      </div>
    </div>
    <template v-else>
      <Header />
      <router-view />
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from "vue";
import Header from "./components/headerMenu/Header.vue";
import useHoldLoginState from "./composables/certification/useHoldLoginState";
import useMaintenanceMode from "./composables/useMaintenanceMode";

//ログイン状態をリロードしても維持するため
const { holdLoginState } = useHoldLoginState();
const { isMaintenance } = useMaintenanceMode();

// async await を使わないとユーザ情報取得する前にMountedサイクルが終了してしまう
onMounted(async () => {
  if (isMaintenance.value) {
    return;
  }
  await holdLoginState();
});
</script>

<style>
/* autocomplete時に入力エリアの色が変わるのを阻止 */
input:-webkit-autofill,
textarea:-webkit-autofill,
select:-webkit-autofill {
  box-shadow: 0 0 0 1000px white inset !important;
  -webkit-text-fill-color: black !important;
}
</style>

// 以下で指定しているstyleはModalのため動的に追加される要素であるためdeepを指定
<style deep>
.vm-wrapper {
  display: flex;
  align-items: center;
}
.vm-wrapper .vm {
  top: auto;
}
</style>
