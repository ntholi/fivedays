import React from 'react';
import { signIn, signOut, useSession } from 'next-auth/react';
import { Button, Center, TextInput } from '@mantine/core';
import { useRouter } from 'next/router';

export default function LoginPage() {
  const { data: session, status } = useSession();

  if (session) {
    return (
      <Center>
        <h1>Welcome {session.user?.name}</h1>;
        <Button onClick={() => signOut()}>Sing Out</Button>
      </Center>
    );
  }

  return (
    <Center>
      <Button onClick={() => signIn()}>Sign in</Button>
    </Center>
  );
}
