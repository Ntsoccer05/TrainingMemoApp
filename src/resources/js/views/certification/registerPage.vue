<template>
  <section class="h-screen">
    <div class="h-full px-6 py-24">
      <div
        class="g-6 flex h-full flex-wrap items-center justify-center lg:justify-between"
      >
        <!-- Left column container with background-->
        <div class="mb-12 md:mb-0 md:w-8/12 hidden lg:w-6/12 lg:block h-screen">
          <img
            src="../../../images/charles-gaudreault-xXofYCc3hqc-unsplash.jpg"
            class="w-full"
            alt="Phone image"
          />
        </div>

        <!-- Right column container with form -->
        <div class="h-screen md:w-8/12 lg:ml-6 lg:w-5/12 w-full">
          <Register />
          <!-- Divider -->
          <div
            class="my-4 flex items-center before:mt-0.5 before:flex-1 before:border-t before:border-neutral-300 after:mt-0.5 after:flex-1 after:border-t after:border-neutral-300"
          >
            <p class="mx-4 mb-0 text-center font-semibold dark:text-neutral-600">OR</p>
          </div>

          <!-- Social login buttons -->
          <SocialLogin />

          <!--Login button-->
          <div class="pb-6 mt-12">
            <!-- Divider -->
            <div
              class="my-4 flex items-center before:mt-0.5 before:flex-1 before:border-t before:border-neutral-300 after:mt-0.5 after:flex-1 after:border-t after:border-neutral-300"
            >
              <p class="mx-4 mb-0 text-center font-semibold dark:text-neutral-600">
                登録済みの方は
              </p>
            </div>
            <button
              type="button"
              class="inline-block w-full mx-auto rounded border-2 border-red-500 px-6 pb-[6px] pt-2 text-base font-medium uppercase leading-normal text-red-500 transition duration-150 ease-in-out hover:border-red-600 hover:bg-neutral-500 hover:bg-opacity-10 hover:text-red-600 focus:border-red-600 focus:text-red-600 focus:outline-none focus:ring-0 active:border-red-700 active:text-red-700 dark:hover:bg-neutral-100 dark:hover:bg-opacity-10"
              data-te-ripple-init
              data-te-ripple-color="light"
              @click="toLogin"
            >
              ログイン画面へ
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import Register from "../../components/certification/Register.vue";
import SocialLogin from "../../components/certification/SocialLogin.vue";
import { useRouter } from "vue-router";
import { setSeo } from "../../utils/setSeo";

setSeo("register");

const router = useRouter();
const toLogin = () => router.push("/login");
</script>

<style></style>
