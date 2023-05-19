import ClassCard from '@/components/classes/ClassCard';
import Header from '@/components/layout/Header';
import { axiosInstance } from '@/lib/config/axios';
import { SimpleGrid, Title, Container } from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import { useSession } from 'next-auth/react';
import { useState, useEffect } from 'react';

const ClassesPage = () => {
  const [classes, setClasses] = useState<classroom_v1.Schema$Course[]>([]);
  const { data: session } = useSession();

  useEffect(() => {
    async function getClasses() {
      const response = await axiosInstance(session)?.get(
        '/courses?courseStates=ACTIVE'
      );
      setClasses(response?.data.courses ?? []);
    }
    if (session) {
      getClasses();
    }
  }, [session]);

  return (
    <>
      <Header />
      <Container size="xl">
        <Title order={1} fw={100} mt="md" mb="xl">
          My Classes
        </Title>
        <SimpleGrid
          cols={4}
          breakpoints={[
            { maxWidth: 'md', cols: 2 },
            { maxWidth: 'xs', cols: 1 },
          ]}
        >
          {classes.map((item) => (
            <ClassCard key={item.id} item={item} />
          ))}
        </SimpleGrid>
      </Container>
    </>
  );
};

export default ClassesPage;
