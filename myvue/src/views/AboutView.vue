<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/yup'
import * as yup from 'yup'

const count = ref(10)
const user = reactive({ id: 123, name: 'Mohd Afroj' })
const author = reactive({
  name: 'John Doe',
  books: ['Vue 2 - Advanced Guide', 'Vue 3 - Basic Guide', 'Vue 4 - The Mystery'],
})
const isActive = ref(true)
const hasError = ref(false)
//Start form validation code
// Creates a typed schema for vee-validate
const schema = toTypedSchema(
  yup.object({
    email: yup.string().required().email(),
    password: yup.string().min(6).required(),
  }),
)
const { values, errors, handleSubmit, defineField } = useForm({
  validationSchema: schema,
  initialValues: { email: 'mohd.afroj@rajyasabha.digital', password: 'test@test.com' },
})

const [email, emailAttrs] = defineField('email', {
  props: (state) => ({
    error: state.errors[0],
  }),
})
const [password, passwordAttrs] = defineField('password', {
  props: (state) => ({
    error: state.errors[1],
  }),
})
const onSubmit = handleSubmit((values) => {
  alert(JSON.stringify(values, null, 2))
})
//End form validation code
function increment() {
  count.value++
}
function updateUserReactive() {
  user.name = 'Afroj Mohd'
}
function removeAuthor() {
  author.books.pop()
}
function addAuthor() {
  author.books.push('Hii')
}
</script>
<template>
  <div class="about">
    <h1>This is an about page</h1>
    <p @click="increment">Check Ref: {{ count }}</p>
    <p @click="updateUserReactive">Check Reactive: {{ user.id + ' ' + user.name }}</p>
    <p>Check Author: {{ author.books.length > 0 ? 'Yes' : 'No' }}</p>
    <div @click="removeAuthor">Remove</div>
    <div @click="addAuthor">Add</div>
    <div class="static" :class="{ active: isActive, 'text-danger': hasError }">Check classes</div>
    <form @submit="onSubmit">
      <input type="email" v-model="email" v-bind="emailAttrs" />
      <div>{{ errors.email }}</div>

      <input type="password" v-model="password" v-bind="passwordAttrs" />
      <div>{{ errors.password }}</div>

      <button>Submit</button>
    </form>
    <br />
    <pre>values: {{ values }}</pre>
    <pre>errors: {{ errors }}</pre>
  </div>
</template>

<style>
@media (min-width: 1024px) {
  .about {
    min-height: 100vh;
    align-items: center;
  }
}
</style>
