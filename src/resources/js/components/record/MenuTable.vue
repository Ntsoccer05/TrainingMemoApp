<template>
  <div class="md:flex md:items-start md:flex-wrap md:justify-center">
    <table
      v-for="category in categories"
      :key="category.id"
      class="border border-collapse table-fixed mx-auto mt-3 md:w-5/12 w-11/12"
    >
      <thead>
        <tr class="relative">
          <th class="border">
            <span class="block">{{ category.content }}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr class="relative" v-for="menu in category.menus" :key="menu.id">
          <td class="border">
            <span class="ml-2 block">{{ menu.content }} </span>
          </td>
        </tr>
      </tbody>
    </table>
    <template v-if="isOdd">
      <table
        class="border border-collapse table-fixed mx-auto mt-3 md:w-5/12 w-11/12"
      ></table>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watchEffect } from "vue";
import { Category } from "../../types/trainingMenu";
type props = {
  categories: Category;
};

const props = defineProps<props>();
const categories = ref(computed(() => props.categories));
const isOdd = ref<boolean>(false);
// watchEffectを使用すると中で使っている定義の値が変わった時、また初回レンダリング時に実行される。
watchEffect(() => {
  if (categories.value.length % 2 === 1) {
    isOdd.value = true;
  } else {
    isOdd.value = false;
  }
});
</script>

<style></style>
