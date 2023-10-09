'use client';
import { experimental_useFormStatus as useFormStatus } from 'react-dom';
import { addRubricItem } from './actions';
import { Button, NumberInput, Stack, TextInput } from '@mantine/core';

export default function RubricForm() {
  return (
    <form action={addRubricItem}>
      <Stack>
        <TextInput name='title' label='Title' required />
        <NumberInput name='points' label='Points' required />
        <TextInput name='description' label='Description' />
        <SubmitButton />
      </Stack>
    </form>
  );
}

const SubmitButton = () => {
  const { pending } = useFormStatus();

  return (
    <Button type='submit' loading={pending}>
      Submit
    </Button>
  );
};
