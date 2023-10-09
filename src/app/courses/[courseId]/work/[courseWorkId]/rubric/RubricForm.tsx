'use client';
import { experimental_useFormStatus as useFormStatus } from 'react-dom';
import { addRubricItem } from './actions';
import { Button, NumberInput, Stack, TextInput } from '@mantine/core';
import { useRef } from 'react';

type Props = {
  courseId: string;
  courseWorkId: string;
};
export default function RubricForm({ courseId, courseWorkId }: Props) {
  const addRubricWithId = addRubricItem.bind(null, courseId, courseWorkId);
  const ref = useRef<HTMLFormElement>(null);

  const handleSubmit = async (formData: FormData) => {
    await addRubricWithId(formData);
    ref.current?.reset();
  };

  return (
    <form action={handleSubmit} ref={ref}>
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
      Add
    </Button>
  );
};
