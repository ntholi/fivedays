import ClassCard from '@/components/classes/ClassCard';
import Layout from '@/components/layout/Layout';
import googleClassroom from '@/lib/helpers/googleClassroom';
import { SimpleGrid, Title } from '@mantine/core';
import { classroom_v1, google } from 'googleapis';
import { GetServerSideProps } from 'next';
import { getSession, useSession } from 'next-auth/react';
import { FC } from 'react';

type Props = {
  classes: classroom_v1.Schema$Course[];
};

const ClassesPage: FC<Props> = ({ classes }) => {
  return (
    <Layout>
      <Title order={2} mt='md' mb='xl'>
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
    </Layout>
  );
};

export const getServerSideProps: GetServerSideProps = async (context) => {
  const session = await getSession(context);
  const classroom = googleClassroom(session);

  try {
    const response = await classroom.courses.list();
    const classes = response.data.courses || [];

    return {
      props: {
        classes,
      },
    };
  } catch (err) {
    console.error('Error: ', err);
    return {
      props: {
        classes: [],
      },
    };
  }
};

export default ClassesPage;
