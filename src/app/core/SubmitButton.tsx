'use client';
import { experimental_useFormStatus as useFormStatus } from 'react-dom';
import { Button } from '@mantine/core';

type Props = {
  children: React.ReactNode;
};

export default function SubmitButton({ children }: Props) {
  const { pending } = useFormStatus();
  return (
    <Button type='submit' loading={pending}>
      {children}
    </Button>
  );
}
