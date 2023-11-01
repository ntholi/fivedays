'use client';
import { useFormStatus } from 'react-dom';
import { Button } from '@mantine/core';
import { ButtonProps } from '@mantine/core';

export default function SubmitButton({ children, ...props }: ButtonProps) {
  const { pending } = useFormStatus();
  return (
    <Button type='submit' {...props} loading={pending}>
      {children}
    </Button>
  );
}
